<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use DB;

class PlansController extends Controller{

	public function getAllPlans()
	{
		$plans = DB::table('plans')->get();
		
		return response()->json($plans);
	}







}