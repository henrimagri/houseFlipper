<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\{User,PlatformEvent,PlatformIncident,PlatformControl};
use App\Services\Platform\{EventRecorder,PlatformQueries};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Hash};
class PlatformSecurityController extends Controller {
    public function __construct(private EventRecorder $events,private PlatformQueries $queries) {}
    public function incidents(Request $r) { $f=$this->queries->filters($r);$q=DB::table('platform_incidents')->whereBetween('created_at',[$f['from'],$f['to']]);if(!empty($f['status']))$q->where('status',$f['status']);if(!empty($f['severity']))$q->where('severity',$f['severity']);return response()->json($this->queries->page($q->latest('id'),$r)); }
    public function storeIncident(Request $r) {
        $d=$r->validate(['title'=>'required|string|max:180','severity'=>'required|in:info,low,medium,high,critical','event_ids'=>'array|max:30','event_ids.*'=>'integer|exists:platform_events,id']);
        return DB::transaction(function()use($d,$r){$i=PlatformIncident::create($d+['created_by'=>$r->user()->id]);$this->events->audit($r,'admin.incident.created','incident',$i->id,[],['status'=>'open']);return response()->json(['data'=>$i],201);});
    }
    public function updateIncident(Request $r,int $id) {
        $d=$r->validate(['status'=>'required|in:open,investigating,mitigated,resolved,false_positive','assigned_user_id'=>'nullable|integer|exists:platform_admins,user_id','resolution'=>'nullable|string|max:1000']);
        if(in_array($d['status'],['resolved','false_positive']))$r->validate(['resolution'=>'required|string|min:5|max:1000']);
        return DB::transaction(function()use($r,$id,$d){$i=PlatformIncident::lockForUpdate()->findOrFail($id);$before=$i->only('status','assigned_user_id');$i->update($d);$this->events->audit($r,'admin.incident.updated','incident',$id,$before,$i->only('status','assigned_user_id'));return response()->json(['data'=>$i]);});
    }
    public function sessions(Request $r) { $f=$this->queries->filters($r);$q=DB::table('personal_access_tokens')->where('tokenable_type',User::class)->select('id','tokenable_id as user_id','created_at','last_used_at','expires_at');if(!empty($f['user_id']))$q->where('tokenable_id',$f['user_id']);if(!empty($f['tenant_id']))$q->whereIn('tokenable_id',DB::table('users')->select('id')->where('tenant_id',$f['tenant_id']));if(!empty($f['product']))$q->whereIn('tokenable_id',DB::table('users')->select('id')->whereIn('tenant_id',DB::table('tenant_product_subscriptions')->select('tenant_id')->where('product_slug',$f['product'])));return response()->json($this->queries->page($q->orderByDesc('id'),$r)+['note'=>'Tokens Sanctum; IP/dispositivo não são associados a tokens legados. Expiração não comprova logout.']); }
    private function confirm(Request $r): array {
        $d=$r->validate(['confirmed'=>'required|accepted','reason'=>'required|in:security_review,customer_request,policy_violation,qa_validation','password'=>'required|string|max:200']);
        abort_unless(Hash::check($d['password'],$r->user()->password),403,'Reautenticação inválida.'); return $d;
    }
    public function action(Request $r) {
        $d=$this->confirm($r);$a=$r->validate(['action'=>'required|in:suspend_user,restore_user,revoke_session,revoke_all_sessions,ip_watch,ip_trust,ip_block,ip_unblock','target'=>'required|string|max:80']);
        return DB::transaction(function()use($r,$d,$a){$target=$a['target'];$action=$a['action'];$before=[];$after=[];
            if(str_starts_with($action,'ip_')){
                abort_unless(preg_match('/^[a-f0-9]{64}$/',$target)&&PlatformEvent::where('ip_hash',$target)->exists(),422,'Selecione o hash de um IP observado.');
                $state=match($action){'ip_block'=>'blocked','ip_watch'=>'watch','ip_trust'=>'trusted',default=>'none'};
                $control=PlatformControl::where('kind','ip')->where('key',$target)->lockForUpdate()->first();$before=['status'=>$control?->value['status']??'none'];
                PlatformControl::updateOrCreate(['kind'=>'ip','key'=>$target],['value'=>['status'=>$state],'expires_at'=>now()->addHours(1)]);$after=['status'=>$state];
            }elseif(in_array($action,['suspend_user','restore_user','revoke_all_sessions'])){
                abort_unless(ctype_digit($target),422);$user=User::lockForUpdate()->findOrFail($target);
                abort_if($user->id===$r->user()->id||$user->isPlatformAdmin(),422,'Operação não permitida sobre administrador da plataforma.');
                if($action==='revoke_all_sessions')$user->tokens()->delete();else{$before=['ativo_flg'=>$user->ativo_flg];$user->update(['ativo_flg'=>$action==='restore_user']);$after=['ativo_flg'=>$user->ativo_flg];if($action==='suspend_user')$user->tokens()->delete();}
            }else{abort_unless(ctype_digit($target),422);$token=\Laravel\Sanctum\PersonalAccessToken::lockForUpdate()->findOrFail($target);abort_unless($token->tokenable_type===User::class,422);$owner=User::findOrFail($token->tokenable_id);abort_if($owner->isPlatformAdmin(),422,'Revogue sua própria sessão na área da conta.');$token->delete();}
            $this->events->audit($r,'admin.'.$action,'operator_target',$target,$before,$after,$d['reason']);return response()->json(['message'=>'Ação administrativa concluída e auditada.']);
        });
    }
    public function ips(Request $r) { $f=$this->queries->filters($r);$q=$this->queries->scope(DB::table('platform_events'),$f)->whereNotNull('ip_hash')->whereIn('category',['api','security']);
        $q->selectRaw("ip_hash,MAX(ip_mask) as ip_mask,MIN(created_at) as first_seen,MAX(created_at) as last_seen,count(*) as observations,SUM(case when category = 'security' then 1 else 0 end) as signals")->groupBy('ip_hash')->orderByDesc('last_seen');
        $result=$this->queries->page($q,$r);foreach($result['data']as$row){$row->risk_score=min(100,$row->signals*5);$row->risk_rule='min(100, sinais de segurança no período × 5); observação, não confirmação de ataque';$row->control=PlatformControl::where('kind','ip')->where('key',$row->ip_hash)->where('expires_at','>',now())->value('value');}return response()->json($result); }
    public function event(Request $r,int $id) { $e=PlatformEvent::findOrFail($id);$q=PlatformEvent::whereBetween('created_at',[$e->created_at->copy()->subMinutes(10),$e->created_at->copy()->addMinutes(10)])->where('ip_hash',$e->ip_hash)->whereIn('category',['security','auth','audit']);return response()->json(['event'=>$e,'related'=>$q->orderBy('id')->limit(100)->get(),'correlation'=>'Mesmo hash de IP e janela de ±10 min; não comprova mesmo usuário.']); }
    public function support(Request $r,int $id) { User::findOrFail($id);$d=$r->validate(['note'=>'required|string|max:1000']);return DB::transaction(function()use($r,$id,$d){$c=PlatformControl::updateOrCreate(['kind'=>'support','key'=>(string)$id],['value'=>['note'=>$d['note'],'author'=>$r->user()->id,'at'=>now()->toIso8601String()]]);$this->events->audit($r,'admin.support.note_saved','user',$id);return response()->json(['data'=>$c->value]);}); }
}
