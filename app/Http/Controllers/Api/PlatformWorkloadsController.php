<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformQueries;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class PlatformWorkloadsController extends Controller {
 public function __construct(private PlatformQueries $queries){}
 public function ai(Request $r){$f=$this->queries->filters($r);$q=$this->queries->scope(DB::table('ai_tool_runs'),$f,'product_slug');if(!empty($f['status']))$q->where('status',$f['status']);
  $groups=(clone$q)->selectRaw('provider,model,tool,status,product_slug,count(*) as runs,avg(latency_ms) as average_latency_ms,sum(input_tokens) as input_tokens,sum(output_tokens) as output_tokens,sum(estimated_cost_usd) as recorded_estimated_cost_usd')->groupBy('provider','model','tool','status','product_slug')->limit(200)->get();
  $sessions=$this->queries->scope(DB::table('ai_search_sessions'),$f,'product_slug');
  return response()->json(['data'=>$groups,'sessions'=>(clone$sessions)->count(),'messages'=>DB::table('ai_search_messages')->whereIn('ai_search_session_id',$sessions->select('id'))->count(),'note'=>'Metadados das ferramentas. Tokens/custos nulos significam não medidos. Não é faturamento nem conteúdo das conversas.','configured_models'=>['provider'=>config('ai.provider'),'local_model'=>config('ai.local.model'),'embedding_model'=>config('ai.embeddings.model'),'vision'=>['DETR','SAM 2','Depth Anything V2']],'model_runtime'=>'Ver health; configuração não comprova modelo carregado.']);
 }
 public function tools(Request $r){$f=$this->queries->filters($r);$q=$this->queries->scope(DB::table('ai_tool_runs'),$f,'product_slug');if(!empty($f['status']))$q->where('status',$f['status']);return response()->json($this->queries->page($q->select('id','user_id','tenant_id','product_slug','agent','tool','status','latency_ms','provider','model','input_tokens','output_tokens','estimated_cost_usd','error_code','created_at')->latest('id'),$r));}
 public function rag(Request $r){$f=$this->queries->filters($r);$q=$this->queries->scope(DB::table('ai_tool_runs'),$f,'product_slug')->where('tool','retrieve_knowledge');return response()->json(['queries'=>$q->count(),'failures'=>(clone$q)->where('status','failed')->count(),'average_latency_ms'=>(clone$q)->avg('latency_ms'),'documents'=>null,'chunks'=>null,'vector_enabled'=>(bool)config('ai.vector.enabled'),'note'=>'Contagem global de documentos não é atribuída a um tenant sem política de escopo. Consulte Health para Qdrant; presença de configuração não comprova indexação.']);}
}
