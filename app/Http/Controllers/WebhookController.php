<?php

namespace App\Http\Controllers;

use App\Subs;
use App\dbxUser;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Model;

use Spatie\FlysystemDropbox\DropboxAdapter;
use League\Flysystem\Filesystem;
use Spatie\Dropbox\Client;
use DB;


class WebhookController extends Controller{

	public function __construct(){
		// $this->middleware('oauth', ['except' => ['verify']]);
		// $this->middleware('authorize:' . __CLASS__, ['except' => ['verify']]);
	}

	public function index($uid){

		$subs = Subs::where('owner', '=', $uid)->get();

		if(!$subs){
			return $this->error("The user has no subs", 404);
		}
		return $this->success($subs, 200);
	}

	public function webhookverify(Request $request)
	{

		return ($request->challenge);
	}
	public function webhook(Request $request)
	{

		$data = file_get_contents('php://input');

		$accounts = json_decode($data)->list_folder->accounts;

		foreach ($accounts as $account) {
		   $dbxuser = DB::table('dbxqueue')->where('dbid','=',$account)->first();
		   	  if($dbxuser == null)
		   	  {
				DB::table('dbxqueue')->insert(
				    ['dbid' => $account]
				);
			  }
			  else
			  {
			  	DB::table('dbxqueue')->update(
				    ['dbid' => $account,'status' => 0]
				);
			  }
			
			var_dump(file_put_contents("./test.log", $account));
		}
		

		return ($request);
	}
	public function list()
	{
		$subs = Subs::all();
		return $this->success($subs, 200);
	}

	public function dropboxChanges()
	{
		    $dbxusers = DB::table('dbxqueue')->get();

			$check_dbxusers = count($dbxusers);

			if($check_dbxusers != 0)
			{

			 foreach ($dbxusers as $key => $user) {

			 if($user->status == 0)
			 {

			   	$path = $user->dbid;

			   	if (!file_exists(base_path().'/public/dropbox-files/'.$path)) {

			   		mkdir(base_path().'/public/dropbox-files/'.$path, 0777, true);
				}
			   	

			  
			   	
				$dbxuid = dbxUser::where('dbid', '=', $user->dbid)->first();
				$dbxUserController = new dbxUserController;
				$dbxaccessToken = $dbxUserController->getToken($dbxuid->uid);

				$client = new Client($dbxaccessToken);

					if($user->cursor == null)
					{
					    $files = $client->listFolder('/apps/subely');

					    if($files['has_more'] != 'false')
					    {
					    	$list_continue = $client->listFolderContinue($files['cursor']);

					    	$check_list_continue = count($list_continue['entries']);

					    	if($check_list_continue != 0)
					    	{
					    		foreach ($list_continue['entries'] as $list_file) {
					    			array_push($files['entries'], $list_file);
					    		}
					    	}
					    }

					}
					else
					{
						$files = $client->listFolderContinue($user->cursor);
					}

					DB::table('dbxqueue')->where('dbid','=',$user->dbid)->update(
						    ['cursor' => $files['cursor'],'status' => 1]
						);

					    

					    $check_entries = count($files['entries']);
							    if($check_entries != 0)
							    {
							    	foreach($files['entries'] as $file)
							    	{

							    		$download = $client->download($file['path_lower']);	

										file_put_contents(base_path().'/public/dropbox-files/'.$path.'/'.$file['name'], $download);
										
										
							    	}
							   	}
					}
				}

				return response()->json('files downloaded successfully');

			}
			else
			{
				return response()->json('No Users found in quene');
			}

	}

}