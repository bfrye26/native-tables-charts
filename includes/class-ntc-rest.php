<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class NTC_REST {
	private NTC_Repository $repo;
	public function __construct( NTC_Repository $repo ) { $this->repo=$repo; }

	public function register(): void {
		register_rest_route('ntc/v1','/datasets',array(
			array('methods'=>'GET','callback'=>array($this,'datasets_list'),'permission_callback'=>array($this,'can_edit')),
			array('methods'=>'POST','callback'=>array($this,'datasets_create'),'permission_callback'=>array($this,'can_create')),
		));
		register_rest_route('ntc/v1','/datasets/(?P<id>\d+)',array(
			array('methods'=>'GET','callback'=>array($this,'dataset_get'),'permission_callback'=>array($this,'can_edit')),
			array('methods'=>array('PUT','PATCH'),'callback'=>array($this,'dataset_update'),'permission_callback'=>array($this,'can_edit')),
			array('methods'=>'DELETE','callback'=>array($this,'dataset_delete'),'permission_callback'=>array($this,'can_delete')),
		));
		register_rest_route('ntc/v1','/datasets/(?P<id>\d+)/rows',array(
			array('methods'=>'GET','callback'=>array($this,'rows_get'),'permission_callback'=>array($this,'can_edit')),
			array('methods'=>array('PUT','PATCH'),'callback'=>array($this,'rows_save'),'permission_callback'=>array($this,'can_edit')),
		));
		register_rest_route('ntc/v1','/views',array(
			array('methods'=>'GET','callback'=>array($this,'views_list'),'permission_callback'=>array($this,'can_edit')),
			array('methods'=>'POST','callback'=>array($this,'views_create'),'permission_callback'=>array($this,'can_create')),
		));
		register_rest_route('ntc/v1','/views/(?P<id>\d+)',array(
			array('methods'=>'GET','callback'=>array($this,'view_get'),'permission_callback'=>array($this,'can_edit')),
			array('methods'=>array('PUT','PATCH'),'callback'=>array($this,'view_update'),'permission_callback'=>array($this,'can_edit')),
			array('methods'=>'DELETE','callback'=>array($this,'view_delete'),'permission_callback'=>array($this,'can_delete')),
		));
		register_rest_route('ntc/v1','/presets',array(
			array('methods'=>'GET','callback'=>array($this,'presets_list'),'permission_callback'=>'__return_true'),
			array('methods'=>'POST','callback'=>array($this,'preset_create'),'permission_callback'=>array($this,'can_presets')),
		));
		register_rest_route('ntc/v1','/presets/(?P<id>\d+)',array(array('methods'=>'DELETE','callback'=>array($this,'preset_delete'),'permission_callback'=>array($this,'can_presets'))));
		register_rest_route('ntc/v1','/import',array(array('methods'=>'POST','callback'=>array($this,'import_data'),'permission_callback'=>array($this,'can_import'))));
		register_rest_route('ntc/v1','/export/(?P<id>\d+)',array(array('methods'=>'GET','callback'=>array($this,'export_data'),'permission_callback'=>array($this,'can_export'))));
	}

	public function can_edit(): bool { return current_user_can('ntc_edit_datasets') || current_user_can('manage_options'); }
	public function can_create(): bool { return current_user_can('ntc_create_datasets') || current_user_can('manage_options'); }
	public function can_delete(): bool { return current_user_can('ntc_delete_datasets') || current_user_can('manage_options'); }
	public function can_presets(): bool { return current_user_can('ntc_manage_presets') || current_user_can('manage_options'); }
	public function can_import(): bool { return current_user_can('ntc_import') || current_user_can('manage_options'); }
	public function can_export(): bool { return current_user_can('ntc_export') || current_user_can('manage_options'); }

	public function datasets_list(WP_REST_Request $r): WP_REST_Response { return rest_ensure_response($this->repo->list_datasets((int)($r['per_page']?:100),(int)($r['offset']?:0),(string)($r['search']?:''))); }
	public function datasets_create(WP_REST_Request $r) {
		$p=$r->get_json_params();$id=$this->repo->create_dataset((string)($p['name']??''),(array)($p['columns']??array()),(array)($p['rows']??array()),(string)($p['description']??''));
		if(!$id)return new WP_Error('ntc_create_failed',__('Could not create dataset.','native-tables-charts'),array('status'=>500));
		return rest_ensure_response($this->repo->get_dataset($id,true));
	}
	public function dataset_get(WP_REST_Request $r){$d=$this->repo->get_dataset((int)$r['id'],false);return $d?rest_ensure_response($d):new WP_Error('ntc_not_found',__('Dataset not found.','native-tables-charts'),array('status'=>404));}
	public function dataset_update(WP_REST_Request $r){$ok=$this->repo->update_dataset((int)$r['id'],(array)$r->get_json_params());return rest_ensure_response(array('success'=>$ok));}
	public function dataset_delete(WP_REST_Request $r){return rest_ensure_response(array('success'=>$this->repo->delete_dataset((int)$r['id'])));}
	public function rows_get(WP_REST_Request $r): WP_REST_Response {$id=(int)$r['id'];$limit=(int)($r['limit']?:0);$offset=(int)($r['offset']?:0);return rest_ensure_response(array('rows'=>$this->repo->get_rows($id,$limit,$offset),'total'=>$this->repo->row_count($id)));}
	public function rows_save(WP_REST_Request $r){$p=(array)$r->get_json_params();$id=(int)$r['id'];if(!empty($p['replace'])){$ok=$this->repo->replace_rows($id,(array)($p['rows']??array()));}elseif(isset($p['indexedRows'])){$ok=$this->repo->patch_rows($id,(array)$p['indexedRows']);}else{$ok=$this->repo->upsert_rows($id,(array)($p['rows']??array()),absint($p['startIndex']??0));}return rest_ensure_response(array('success'=>$ok,'total'=>$this->repo->row_count($id)));}
	public function views_list(WP_REST_Request $r): WP_REST_Response {$dataset=isset($r['dataset_id'])?(int)$r['dataset_id']:null;return rest_ensure_response($this->repo->list_views($dataset,(string)($r['type']?:'')));}
	public function views_create(WP_REST_Request $r){$p=(array)$r->get_json_params();$id=$this->repo->create_view(absint($p['dataset_id']??0),(string)($p['type']??'table'),(string)($p['name']??''),(array)($p['config']??array()));return rest_ensure_response($this->repo->get_view($id));}
	public function view_get(WP_REST_Request $r){$v=$this->repo->get_view((int)$r['id']);return $v?rest_ensure_response($v):new WP_Error('ntc_not_found',__('View not found.','native-tables-charts'),array('status'=>404));}
	public function view_update(WP_REST_Request $r){return rest_ensure_response(array('success'=>$this->repo->update_view((int)$r['id'],(array)$r->get_json_params())));}
	public function view_delete(WP_REST_Request $r){return rest_ensure_response(array('success'=>$this->repo->delete_view((int)$r['id'])));}
	public function presets_list(WP_REST_Request $r): WP_REST_Response {return rest_ensure_response(array('custom'=>$this->repo->list_presets((string)($r['type']?:'')),'tableBuiltins'=>NTC_Renderer::table_presets(),'chartBuiltins'=>NTC_Renderer::chart_presets()));}
	public function preset_create(WP_REST_Request $r){$p=(array)$r->get_json_params();$id=$this->repo->create_preset((string)($p['type']??'table'),(string)($p['name']??__('Custom preset','native-tables-charts')),(array)($p['settings']??array()));return rest_ensure_response(array('id'=>$id));}
	public function preset_delete(WP_REST_Request $r){return rest_ensure_response(array('success'=>$this->repo->delete_preset((int)$r['id'])));}

	public function import_data(WP_REST_Request $r){
		$p=(array)$r->get_json_params();$format=sanitize_key($p['format']??'csv');$raw=(string)($p['data']??'');$name=(string)($p['name']??__('Imported Dataset','native-tables-charts'));
		try{$parsed=$this->parse_import($raw,$format);}catch(Exception $e){return new WP_Error('ntc_import_error',$e->getMessage(),array('status'=>400));}
		$id=$this->repo->create_dataset($name,$parsed['columns'],$parsed['rows']);return rest_ensure_response(array('id'=>$id,'columns'=>$parsed['columns'],'rows'=>$parsed['rows']));
	}

	private function parse_import(string $raw,string $format): array {
		if('json'===$format){
			$data=json_decode($raw,true);if(!is_array($data))throw new Exception(__('Invalid JSON.','native-tables-charts'));
			if(isset($data['columns'],$data['rows'])){
				$cols=$this->repo->sanitize_columns((array)$data['columns']);$width=count($cols);$rows=array_slice((array)$data['rows'],0,10000);
				$rows=array_map(fn($row)=>array_slice(array_pad((array)$row,$width,''),0,$width),$rows);
				return array('columns'=>$cols,'rows'=>$rows);
			}
			$rows=array_slice(array_values($data),0,10000);if(!$rows)return array('columns'=>array(),'rows'=>array());
			if(array_is_list($rows[0])){$width=min(40,count($rows[0]));if($width<1)return array('columns'=>array(),'rows'=>array());$cols=array_map(fn($i)=>array('id'=>'c'.($i+1),'label'=>'Column '.($i+1),'type'=>'auto','unit'=>''),range(0,$width-1));$rows=array_map(fn($row)=>array_slice(array_pad((array)$row,$width,''),0,$width),$rows);return array('columns'=>$cols,'rows'=>$rows);}
			$keys=array_slice(array_keys((array)$rows[0]),0,40);$cols=array_map(fn($k)=>array('id'=>sanitize_key((string)$k),'label'=>sanitize_text_field((string)$k),'type'=>'auto','unit'=>''),$keys);return array('columns'=>$cols,'rows'=>array_map(fn($r)=>array_map(fn($k)=>(array_key_exists($k,(array)$r)?$r[$k]:''),$keys),$rows));
		}
		$delimiter='tsv'===$format?"	":',';$matrix=array();$fh=fopen('php://temp','r+');if(false===$fh)throw new Exception(__('Could not parse the import.','native-tables-charts'));fwrite($fh,$raw);rewind($fh);while(($record=fgetcsv($fh,0,$delimiter,'"',''))!==false){if(count($record)===1&&trim((string)$record[0])==='')continue;$matrix[]=$record;if(count($matrix)>10001)break;}fclose($fh);if(!$matrix)return array('columns'=>array(),'rows'=>array());$header=array_shift($matrix);$header=array_slice($header,0,40);$cols=array();foreach($header as $i=>$h)$cols[]=array('id'=>sanitize_key($h?:'c'.($i+1)),'label'=>sanitize_text_field($h?:'Column '.($i+1)),'type'=>'auto','unit'=>'');$width=count($cols);$rows=array_slice(array_map(fn($row)=>array_slice(array_pad((array)$row,$width,''),0,$width),$matrix),0,10000);return array('columns'=>$cols,'rows'=>$rows);
	}

	public function export_data(WP_REST_Request $r){$d=$this->repo->get_dataset((int)$r['id'],true);if(!$d)return new WP_Error('ntc_not_found',__('Dataset not found.','native-tables-charts'),array('status'=>404));$format=sanitize_key($r['format']?:'json');if('json'===$format)return rest_ensure_response(array('name'=>$d['name'],'columns'=>$d['columns'],'rows'=>$d['rows']));$delimiter='tsv'===$format?"\t":',';$fh=fopen('php://temp','r+');fputcsv($fh,array_map(fn($c)=>$c['label']??'',$d['columns']),$delimiter,'"','');foreach($d['rows'] as $row)fputcsv($fh,$row,$delimiter,'"','');rewind($fh);$body=stream_get_contents($fh);fclose($fh);return new WP_REST_Response($body,200,array('Content-Type'=>'tsv'===$format?'text/tab-separated-values':'text/csv','Content-Disposition'=>'attachment; filename="'.sanitize_file_name($d['name']).'.'.$format.'"'));}
}
