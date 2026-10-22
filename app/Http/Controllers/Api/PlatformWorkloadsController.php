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

 public function jobs(Request $r){$f=$this->queries->filters($r);$sf=$f;unset($sf['product']);$q=$this->queries->scope(DB::table('property_processing_jobs'),$sf);if(!empty($f['product'])&&$f['product']!=='house_flipper')$q->whereRaw('1=0');if(!empty($f['status']))$q->where('status',$f['status']);
  $q->select('id','tenant_id','user_id','property_visualization_id','worker_job_id','kind','status','result','started_at','completed_at','created_at');$page=$this->queries->page($q->latest('id'),$r);
  foreach($page['data']as$row){$result=json_decode($row->result??'{}',true)??[];unset($row->result);$row->actual_method=data_get($result,'actual_method')??data_get($result,'geometry_quality.method');$row->progress=data_get($result,'progress');$row->photo_count=null;$row->quality=[];
   foreach(['vertices','faces','components','significant_components','completeness_10cm','precision_10cm','open_edges','non_manifold_edges','textured_face_ratio','final_model_eligible','viewer_eligible']as$key){$v=data_get($result,'geometry_quality.'.$key);$row->quality[$key]=is_numeric($v)||is_bool($v)?$v:null;}
   $row->duration_seconds=$row->started_at&&$row->completed_at?max(0,strtotime($row->completed_at)-strtotime($row->started_at)):null;$row->ram_bytes=null;$row->vram_bytes=null;
  }
  return response()->json($page+['note'=>'Método efetivamente retornado pelo worker; nulo não é zero. Sem retry genérico: operações não idempotentes não podem ser reexecutadas pela central. Cancelamento continua no fluxo autorizado do imóvel.']);
 }
 public function storage(Request $r){$f=$this->queries->filters($r);$q=DB::table('property_visualization_assets as a')->join('property_visualizations as v','v.id','=','a.property_visualization_id')->whereBetween('a.created_at',[$f['from'],$f['to']]);if(!empty($f['tenant_id']))$q->where('v.tenant_id',$f['tenant_id']);if(!empty($f['user_id']))$q->where('v.user_id',$f['user_id']);if(!empty($f['product'])&&$f['product']!=='house_flipper')$q->whereRaw('1=0');return response()->json(['data'=>$q->selectRaw('v.tenant_id,a.kind,sum(a.size_bytes) as bytes,count(*) as files')->groupBy('v.tenant_id','a.kind')->orderByDesc('bytes')->limit(100)->get(),'note'=>'Somente assets registrados do visualizador no período; não é inventário completo de storage ou quota.','quota'=>null]);}
 public function queues(Request $r){return response()->json(['driver'=>config('queue.default'),'data'=>DB::table('jobs')->selectRaw('queue,count(*) as pending,min(available_at) as oldest_available_at')->groupBy('queue')->limit(100)->get(),'failed'=>DB::table('failed_jobs')->count(),'heartbeat'=>null,'throughput'=>null,'note'=>'Fila Laravel persistida. Processos FastAPI são mostrados em Jobs. Sem retry indiscriminado; worker vivo não comprovado por contagem.']);}
}
