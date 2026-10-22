<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformQueries;
use Illuminate\Http\Request;
class PlatformOperationsController extends Controller {
    public function __construct(private PlatformQueries $queries) {}
    public function overview(Request $r) { return response()->json($this->queries->overview($r)); }
    public function clients(Request $r) {return response()->json($this->queries->clients($r));}
    public function client(Request $r,int $id) {return response()->json($this->queries->client($id,$r));}
    public function subscriptions(Request $r) {return response()->json($this->queries->subscriptions($r));}
    public function trials(Request $r) {return response()->json($this->queries->subscriptions($r,'trials'));}
    public function churn(Request $r) {return response()->json($this->queries->subscriptions($r,'churn'));}
    public function funnel(Request $r) {return response()->json($this->queries->funnel($r));}
    public function cohorts(Request $r) {return response()->json($this->queries->cohorts($r));}
    public function events(Request $r) {return response()->json($this->queries->events($r));}
}
