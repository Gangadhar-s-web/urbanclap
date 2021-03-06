<?php

namespace App\Http\Controllers\Merchant;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Models\TableBookingOrder;
use App\Models\TableBookingSettings;
use App\Models\Category;
use App\Models\Listing;
use App\Models\MerchantServices;

use App\Events\TableBookedStatus;
use Response;
use Validator;
use DB;
use Auth;
use Mail;
class TableBookingController extends Controller
{

	var $table_booking_id;
	var $TableBookingSettings;
	var $table_booking_category_id_lists = [];
	
	public function __construct()
	{
		parent::__construct();
		
		$this->TableBookingSettings = new TableBookingSettings;
		$this->table_booking_id = $this->TableBookingSettings->table_booking_id;
		
		$categories = Category::where('category_type', '!=', '')->get();
		foreach($categories as $category) {
			$category_type = json_decode($category->category_type);
			if( in_array($this->table_booking_id, $category_type)  )
				$this->table_booking_category_id_lists[] = $category->c_id;
		}//print_r($this->appointment_booking_category_id_lists);
	}
	public function settings()
	{		
		
		$user_id = (auth()->check()) ? auth()->user()->id : null;
		$listings = Listing::
		where('user_id', $user_id)
		->whereIn('m_c_id', $this->table_booking_category_id_lists)
		->get();
		
		$table_booking_settings = TableBookingSettings::where('merchant_id', $user_id)->first();
		
		if($table_booking_settings == null)
			$table_booking_settings = new TableBookingSettings;
		
		//dd($table_booking_settings);
		
		$times = array();
		for($i = 1; $i <= 12; $i++)
		{
			$times[$i] = $i;
		}
		
		$merchant_services = MerchantServices::where('merchant_id',$user_id)->get();
		
		// check if shop enable or not
		$service_disable = true; // default
		$services = $this->services;
		$shop_id = $services['table']['id'];
		foreach($merchant_services as $merchant_service) {
			$category_type_id = $merchant_service->category_type_id;
			
			if($category_type_id == $shop_id and $merchant_service->is_enable) {
				$service_disable = false; // shop is now enable
				break;
			}
		}
		$listing = Listing::where('user_id', '=', $user_id)->first();
		$listing_id=$listing->id;
		//return view('panels.merchant.table-booking.settings', compact('table_booking_settings', 'listings', 'times', 'user_id', 'service_disable'));
		return $this->_loadMerchantView('table-booking.settings', compact('table_booking_settings', 'listings', 'times', 'user_id', 'service_disable','listing_id'));
	}
	
	public function enable()
	{
		$merchant_service_id = null;
		
		$services = $this->services;
		
		$shop_category_type_id = $services['table']['id'];
			
		$merchant_id=Auth::user()->id;
		$merchant_services = MerchantServices::where('merchant_id',$merchant_id)->get();
		foreach($merchant_services as $merchant_service) {
			$category_type_id = $merchant_service->category_type_id;
			
			if($category_type_id == $shop_category_type_id) {
				$merchant_service_id = $merchant_service->id;
				break;
			}
		}
		
		$merchant_id = (auth()->check()) ? auth()->user()->id : null;
		
		if($merchant_service_id)
		{
			$merchant_service = MerchantServices::find($merchant_service_id);
			$merchant_service->category_type_id = $shop_category_type_id;
			$merchant_service->merchant_id = $merchant_id;
			$merchant_service->is_enable = 1;
			$merchant_service->save();
		}
		else
		{
			$merchant_service = new MerchantServices;
			$merchant_service->category_type_id = $shop_category_type_id;
			$merchant_service->merchant_id = $merchant_id;
			$merchant_service->is_enable = 1;
			$merchant_service->save();
		}
		
		return redirect('merchant/table-booking/settings')->with('message', 'Service activated successfully');
	}
	
	public function disable()
	{	 
		$merchant_service_id = null;
		
		$services = $this->services;
		
		$shop_category_type_id = $services['table']['id'];
			
		$merchant_id=Auth::user()->id;
		$merchant_services = MerchantServices::where('merchant_id',$merchant_id)->get();
		foreach($merchant_services as $merchant_service) {
			$category_type_id = $merchant_service->category_type_id;
			
			if($category_type_id == $shop_category_type_id) {
				$merchant_service_id = $merchant_service->id;
				break;
			}
		}
		
		
		$merchant_id = (auth()->check()) ? auth()->user()->id : null;
		
		if($merchant_service_id)
		{
			$merchant_service = MerchantServices::find($merchant_service_id);
			$merchant_service->category_type_id = $shop_category_type_id;
			$merchant_service->merchant_id = $merchant_id;
			$merchant_service->is_enable = 0;
			$merchant_service->save();
		}
		else
		{
			$merchant_service = new MerchantServices;
			$merchant_service->category_type_id = $shop_category_type_id;
			$merchant_service->merchant_id = $merchant_id;
			$merchant_service->is_enable = 0;
			$merchant_service->save();
		}
		
		return redirect('merchant/table-booking/settings')->with('message', 'Service de-activated successfully');
	}
	
	public function getBookings(){
		$user_id=Auth::user()->id;
		$allBookings=TableBookingOrder::orderBy('order_date','desc')->where('merchant_id',$user_id)->get();
		return view('panels.merchant.tablebooking',compact('allBookings'));
	}
	public function enableBooking(Request $request)
	{
		$tablebooking = TableBookingOrder::find($request->id);
		
		if($tablebooking)
		{
			$tablebooking->status = 1;
			$tablebooking->save();		
			
			event(new TableBookedStatus($tablebooking));
			
			\Session::flash('message', 'Order accepted successfully.');

			return \Response::json(array(
					'success' => true,
				'msg'   => 'Success'
				));
		}
		
		return \Response::json(array(
			'success' => false,
			'msg'   => 'Invalid Id'
			));
	}
	public function disableBooking(Request $request)
	{
		$tablebooking = TableBookingOrder::find($request->id);
		
		if($tablebooking)
		{
			$tablebooking->status = 2;
			$tablebooking->save();
			
			event(new TableBookedStatus($tablebooking));
			
			\Session::flash('message', 'Order declined.');

			return \Response::json(array(
					'success' => true,
					'msg'   => 'Success'
					));
		}
		
		return \Response::json(array(
			'success' => false,
			'msg'   => 'Invalid Id'
			));
	}
	public function add(Request $request)
	{		

		$rules = $this->TableBookingSettings->get_adding_rules();
		$this->validate($request, $rules['rules'], $rules['messages']);
		
		$user_id = (auth()->check()) ? auth()->user()->id : null;
		
		// add
		$this->TableBookingSettings->listing_id = $request->listing_id;
		$this->TableBookingSettings->merchant_id = $user_id;
		$this->TableBookingSettings->start_time = $request->start_time.$request->start_time_ar;
		$this->TableBookingSettings->end_time = $request->end_time.$request->end_time_ar;
		$this->TableBookingSettings->time_interval = $request->time_interval;
		$this->TableBookingSettings->people_limit = $request->people_limit;
		$this->TableBookingSettings->status=$request->status;
		
		$holidays = json_encode(array());
		if($request->holidays)
			$holidays = json_encode($request->holidays);
		
		$this->TableBookingSettings->holidays=$holidays;
		
		$this->TableBookingSettings->save();
		
		return redirect('merchant/table-booking/settings')->with('message', 'Table booking settings added successfully');
	}
	public function edit($id)
	{		

		$return['settings'] = TableBookingSettings::find($id);
		$return['listing'] = $return['settings']->listing;

		// ajax
		return Response::json(array(
			'view_details'   => $return
			));
	}
	public function update(Request $request)
	{	
		$rules = $this->TableBookingSettings->get_adding_rules();
		$this->validate($request, $rules['rules'], $rules['messages']);
		
		$holidays = json_encode(array());
		if($request->holidays)
			$holidays = json_encode($request->holidays);
		
		$listing = Listing::find($request->listing_id);
		
		$booking_settings=array('start_time'=>$request->start_time.$request->start_time_ar, 
			'listing_id' => $request->listing_id,
			'merchant_id' => $listing->user_id,
			'end_time'=>$request->end_time.$request->end_time_ar,
			'time_interval'=>$request->time_interval,
			'people_limit'=>$request->people_limit,
			'holidays'=>$holidays,
			'status'=>$request->status);

		$update_settings=DB::table('table_booking_settings')->where('id',$request->id)->update($booking_settings);
		//DB::enableQueryLog();
		//dd(DB::getQueryLog());
		return redirect('merchant/table-booking/settings')->with('message','Booking settings updated successfully');

	}

	public function destroy($id)
	{
		$settings = TableBookingSettings::find($id);
		
		if($settings)
		{

			$settings->delete();
			
			\Session::flash('message', 'Seltected row deleted successfully.');
			
			return Response::json(array(
				'success' => true,
				'msg'   => 'Seltected row deleted successfully.'
				));
		}
		
		return Response::json(array(
			'success' => false,
			'msg'   => 'Invalid Id'
			));
	}
	public function destroy_all(Request $request)
	{
		$cn = count($request->selected_id);
		if($cn > 0)
		{
			TableBookingSettings::destroy($request->selected_id);	
		}
		
		return redirect('merchant/table-booking')->with('message','Selected settings deleted successfully');

	}
	
}