<?php

namespace App\Http\Controllers;

use App\Subs;
use App\dbxUser;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Model;

use Spatie\FlysystemDropbox\DropboxAdapter;
use Illuminate\Filesystem\Filesystem;
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
		// Added by TMG
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
			  	DB::table('dbxqueue')->where('dbid','=',$account)->update(
				    ['dbid' => $account, 'cursor' => null,'status' => 0]
				);
			  }
			// EOF Added by TMG 
			//var_dump(file_put_contents("./test.log", $account));
		}

		WebhookController::dropboxChanges();
		

		return ($request);
	}
	public function list()
	{
		$subs = Subs::all();
		return $this->success($subs, 200);
	}

	public function dropboxChanges()
	{
		// Added by TMG fetch dropbox files of users with the latest update
		    $dbxusers = DB::table('dbxqueue')->get();

			$check_dbxusers = count($dbxusers);

			$total_files = 0;

			if($check_dbxusers != 0)
			{

			 foreach ($dbxusers as $key => $user) {

			 if($user->status == 0)
			 {

			 	$dbx_user = DB::table('dbx_users')->where('dbid','=',$user->dbid)->first();

			  if($dbx_user != null)
			   {

			 	$paths = DB::table('subs')->where('owner','=',$dbx_user->uid)->get();

			 	$check_path_exists = count($paths);

			 	if($check_path_exists != 0)
			 	{

			 	foreach ($paths as $path){

			   	$filesystem = new Filesystem();

			   	if (file_exists(base_path().'/public/dropbox-files/'.$path->sub_domain)) {

			   		$filesystem->deleteDirectory(base_path().'/public/dropbox-files/'.$path->sub_domain);
				}

			   	if (!file_exists(base_path().'/public/dropbox-files/'.$path->sub_domain)) {

			   		mkdir(base_path().'/public/dropbox-files/'.$path->sub_domain, 0777, true);
				}

			  
			   	
				$dbxuid = dbxUser::where('dbid', '=', $user->dbid)->first();
				$dbxUserController = new dbxUserController;
				$dbxaccessToken = $dbxUserController->getToken($dbxuid->uid);

				$client = new Client($dbxaccessToken);

				$restricted_path = 0;

				$check_folder_exists = 0;

					if($user->cursor == null)
					{
						// catch exception if folder do not exist
							try {
						        $files = $client->listFolder("/apps/subely/".$path->sub_domain);

						        $restricted_path = 0;

								$check_folder_exists = 1;
						    } catch (\Exception $e) {
						    	try{

						    	   $files = $client->listFolder($path->sub_domain);

						    	   $restricted_path = 1;

						    	   $check_folder_exists = 1;

						    	} catch (\Exception $e) {

						    		$check_folder_exists = 0;
						    	}

						    }


					  if($check_folder_exists == 1)
					  {
					    while($files['has_more'] == 'true')
					    {
					    	$list_continue = $client->listFolderContinue($files['cursor']);

					    	$check_list_continue = count($list_continue['entries']);

					    	if($check_list_continue != 0)
					    	{
					    		foreach ($list_continue['entries'] as $list_file) {
					    			array_push($files['entries'], $list_file);
					    		}
					    	}

					    	$files['has_more'] = $list_continue['has_more'];
					    }

					  }

					}
					else
					{
						$files['entries'] = [];

					}
				/*	else
					{
					  try {
							 $files = $client->listFolderContinue($user->cursor);
							 $check_folder_exists = 1;
						   }catch (\Exception $e) {

						   	$check_folder_exists = 0;
						  }
					}
				*/

						if($check_folder_exists == 1)
						{

					    $check_entries = count($files['entries']);
					    $total_files = $check_entries;
							    if($check_entries != 0)
							    {
							    	foreach($files['entries'] as $file)
							    	{
							    		if($file['.tag'] == "folder")
							    		{

							    			if (!file_exists(base_path().'/public/dropbox-files/'.$path->sub_domain.'/'.$file['name'])) {

											mkdir(base_path().'/public/dropbox-files/'.$path->sub_domain.'/'.$file['name'], 0777, true);
											}


							    			$inner_folder_path = '/public/dropbox-files/'.$path->sub_domain.'/'.$file['name'];

							    			$restricted_file_name = "/".$path->sub_domain."/".$file['name'];

							    			$full_file_name = "/apps/subely"."/".$path->sub_domain."/".$file['name'];

											
							    			WebhookController::fetchInnerData($client,$inner_folder_path,$restricted_file_name,$full_file_name,$restricted_path);
											

										}
										else
										{
														   	

							    		$download = $client->download($file['path_lower']);	

										file_put_contents(base_path().'/public/dropbox-files/'.$path->sub_domain.'/'.$file['name'], $download);

										}
										
										
							    	}
							   	}


							 
							DB::table('dbxqueue')->where('dbid','=',$user->dbid)->update(
							    ['cursor' => $files['cursor'],'status' => 1]
							);
						}

					   }

					  }
					}

				   }
				}

				return response()->json($total_files.' new dropbox files downloaded');

			}
			else
			{
				return response()->json('No Users found in que');
			}

			// EOF Added by TMG fetch dropbox files of users with the latest update

	}



	public function fetchInnerData($client,$inner_folder_path,$restricted_file_name,$full_file_name,$restricted_path)
	{

		if($restricted_path == 1)
		  {

		  	$iterator = 0;

		  	try{

				$inner_files = $client->listFolder($restricted_file_name);

				while($inner_files['has_more'] == 'true')
			 	{
					    	$list_continue = $client->listFolderContinue($inner_files['cursor']);

					    	$check_list_continue = count($list_continue['entries']);

					    	if($check_list_continue != 0)
					    	{
					    		foreach ($list_continue['entries'] as $list_file) {
					    			array_push($inner_files['entries'], $list_file);
					    		}
					    	}
					$inner_files['has_more'] = $list_continue['has_more'];
		     	}
				$iterator = 1;

			} catch (\Exception $e) {
				$iterator = 0;
			}

			

		if($iterator == 1)
		{

			$check_inner_files = count($inner_files['entries']);

			if($check_inner_files != 0)
			{

				foreach ($inner_files['entries'] as $inner_file){

					if($inner_file['.tag'] == "folder")
					{

						$inner_folder_path_after = $inner_folder_path."/".$inner_file['name'];

						if (!file_exists(base_path().$inner_folder_path_after)) {

							mkdir(base_path().$inner_folder_path_after, 0777, true);
						}

						$restricted_file_name_after = $restricted_file_name."/".$inner_file['name'];

						$full_file_name_after = $full_file_name."/".$inner_file['name'];

						WebhookController::fetchInnerData($client,$inner_folder_path_after,$restricted_file_name_after,$full_file_name_after,$restricted_path);
					}
					else
					{

						$merged_file_name = $inner_folder_path."/".$inner_file['name'];

						$download = $client->download($inner_file['path_lower']);	

						file_put_contents(base_path().$merged_file_name, $download);

					}
													
				}

			   }

			  }
		   }
		else
		  {

		  	$iterator = 0;

		  	try{

				$inner_files = $client->listFolder($full_file_name);
				while($inner_files['has_more'] == 'true')
			 	{
					    	$list_continue = $client->listFolderContinue($inner_files['cursor']);

					    	$check_list_continue = count($list_continue['entries']);

					    	if($check_list_continue != 0)
					    	{
					    		foreach ($list_continue['entries'] as $list_file) {
					    			array_push($inner_files['entries'], $list_file);
					    		}
					    	}
					$inner_files['has_more'] = $list_continue['has_more'];
		     	}
				$iterator = 1;

			} catch (\Exception $e) {


				$iterator = 0;
			}


		if($iterator == 1)
		{

			$check_inner_files = count($inner_files['entries']);

			if($check_inner_files != 0)
			{
				foreach ($inner_files['entries'] as $inner_file){

					//dd($inner_file);

					if($inner_file[".tag"] == "folder")
					{

						$inner_folder_path_after = $inner_folder_path."/".$inner_file['name'];

						if (!file_exists(base_path().$inner_folder_path_after)) {

							mkdir(base_path().$inner_folder_path_after, 0777, true);
						}


						$restricted_file_name_after = $restricted_file_name."/".$inner_file['name'];

						$full_file_name_after = $full_file_name."/".$inner_file['name'];

						WebhookController::fetchInnerData($client,$inner_folder_path_after,$restricted_file_name_after,$full_file_name_after,$restricted_path);
					}
					else
					{

						$merged_file_name = $inner_folder_path."/".$inner_file['name'];

						$download = $client->download($inner_file['path_lower']);	

						file_put_contents(base_path().$merged_file_name, $download);

					}
													
				}

			}

		  }

		 }
	}

}
