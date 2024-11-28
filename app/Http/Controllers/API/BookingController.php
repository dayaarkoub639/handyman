<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\BookingStatus;
use App\Models\BookingRating;
use App\Models\HandymanRating;
use App\Models\BookingActivity;
use App\Models\Payment;
use App\Models\PaymentHistory;
use App\Models\Wallet;
use App\Models\LiveLocation;
use App\Models\User;
use App\Models\BookingHandymanMapping;
use App\Models\ServiceProof;
use App\Http\Resources\API\BookingResource;
use App\Http\Resources\API\BookingDetailResource;
use App\Http\Resources\API\BookingRatingResource;
use App\Http\Resources\API\ServiceResource;
use App\Http\Resources\API\UserResource;
use App\Http\Resources\API\HandymanResource;
use App\Http\Resources\API\HandymanRatingResource;
use App\Http\Resources\API\ServiceProofResource;
use App\Http\Resources\API\PostJobRequestResource;
use App\Models\BookingServiceAddonMapping;
use App\Traits\NotificationTrait;
use App\Traits\EarningTrait;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Models\Setting;

class BookingController extends Controller
{
    use NotificationTrait;
    use EarningTrait;
    public function getBookingList(Request $request){
        $booking = Booking::myBooking()->with('customer','provider','service','payment');

        if ($request->has('status') && isset($request->status)) {

            $status = explode(',', $request->status);
            $booking->whereIn('status', $status);

         }

         if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $booking->where(function ($query) use ($search) {
                $query->where('id', 'LIKE', "%$search%")

                    ->orWhereHas('service', function ($serviceQuery) use ($search) {
                        $serviceQuery->where('name', 'LIKE', "%$search%");
                    })

                    ->orWhereHas('provider', function ($providerQuery) use ($search) {
                        $providerQuery->where(function ($nameQuery) use ($search) {
                            $nameQuery->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%$search%"])
                                ->orWhere('email', 'LIKE', "%$search");
                        });
                     })

                     ->orWhereHas('customer', function ($userQuery) use ($search) {
                        $userQuery->where(function ($nameQuery) use ($search) {
                            $nameQuery->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%$search%"])
                                ->orWhere('email', 'LIKE', "%$search");
                        });
                    });
            });
        }



        $per_page = config('constant.PER_PAGE_LIMIT');
        if( $request->has('per_page') && !empty($request->per_page)){
            if(is_numeric($request->per_page)){
                $per_page = $request->per_page;
            }
            if($request->per_page === 'all' ){
                $per_page = $booking->count();
            }
        }
        $orderBy = 'desc';
        if( $request->has('orderby') && !empty($request->orderby)){
            $orderBy = $request->orderby;
        }

        $booking = $booking->orderBy('updated_at',$orderBy)->paginate($per_page);
        $items = BookingResource::collection($booking);

        $response = [
            'pagination' => [
                'total_items' => $items->total(),
                'per_page' => $items->perPage(),
                'currentPage' => $items->currentPage(),
                'totalPages' => $items->lastPage(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
                'next_page' => $items->nextPageUrl(),
                'previous_page' => $items->previousPageUrl(),
            ],
            'data' => $items,
        ];

        return comman_custom_response($response);
    }

    public function getBookingDetail(Request $request){

        $id = $request->booking_id;

        $booking_data = Booking::with('customer','provider','service','bookingRating','bookingPostJob','bookingAddonService','bookingPackage','payment')->where('id',$id)->first();

        if($booking_data == null){
            $message = __('messages.booking_not_found');
            return comman_message_response($message,400);
        }
        $booking_detail = new BookingDetailResource($booking_data);

        $rating_data = BookingRatingResource::collection($booking_detail->bookingRating->take(5));
        $service = new ServiceResource($booking_detail->service);
        $customer = new UserResource($booking_detail->customer);
        $provider_data = new UserResource($booking_detail->provider);
        $handyman_data = HandymanResource::collection($booking_detail->handymanAdded);

        $customer_review = null;
        if($request->customer_id != null){
            $customer_review = BookingRating::where('customer_id',$request->customer_id)->where('service_id',$booking_detail->service_id)->where('booking_id',$id)->first();
            if (!empty($customer_review))
            {
                $customer_review = new BookingRatingResource($customer_review);
            }
        }

        if (auth()->check()) {
            $auth_user = auth()->user();
            if (count($auth_user->unreadNotifications) > 0) {
                $auth_user->unreadNotifications->where('data.id', $id)->markAsRead();
            }
        } else {
            return response()->json(['error' => 'User not authenticated'], 401);
        }
        $booking_activity = BookingActivity::where('booking_id',$id)->orderBy('id', 'asc')->get();
        $serviceProof = ServiceProofResource::collection(ServiceProof::with('service','handyman','booking')->where('booking_id',$id)->get());
        $post_job_object = null;
        if($booking_data->type == 'user_post_job'){
            $post_job_object = new PostJobRequestResource($booking_data->bookingPostJob);
        }

        $bookingpackage = $booking_data->bookingPackage;
        if($bookingpackage !== null){
            $mediaUrl = $bookingpackage->package->getFirstMedia('package_attachment')?->getUrl();
            $bookingpackage->package_image = $mediaUrl ?? asset('images/default.png');
        }

        $response = [
            'booking_detail'    => $booking_detail,
            'service'           => $service,
            'customer'          => $customer,
            'booking_activity'  => $booking_activity,
            'rating_data'       => $rating_data,
            'handyman_data'     => $handyman_data,
            'provider_data'     => $provider_data,
            'coupon_data'       => $booking_detail->couponAdded,
            'customer_review'   => $customer_review,
            'service_proof'     => $serviceProof,
            'post_request_detail' => $post_job_object,
            // 'bookingpackage'    => $bookingpackage,
        ];
        return comman_custom_response($response);
    }

    public function saveBookingRating(Request $request)
    {
        $rating_data = $request->all();
        $result = BookingRating::updateOrCreate(['id' => $request->id], $rating_data);

        $message = __('messages.update_form',[ 'form' => __('messages.rating') ] );
		if($result->wasRecentlyCreated){
			$message = __('messages.save_form',[ 'form' => __('messages.rating') ] );
		}

        return comman_message_response($message);
    }

    public function deleteBookingRating(Request $request)
    {
        $user = \Auth::user();

        $book_rating = BookingRating::where('id',$request->id)->where('customer_id',$user->id)->delete();

        $message = __('messages.delete_form',[ 'form' => __('messages.rating') ] );

        return comman_message_response($message);
    }

    public function bookingStatus(Request $request)
    {
        $booking_status = BookingStatus::orderBy('sequence')->get();
        return comman_custom_response($booking_status);
    }

    public function bookingUpdate(Request $request)
    {
        $setting = Setting::getValueByKey('site-setup','site-setup');
        $digitafter_decimal_point = $setting ? $setting->digitafter_decimal_point : "2";
        $data = $request->all();

        $id = $request->id;
        $data['start_at'] = isset($request->start_at) ? date('Y-m-d H:i:s',strtotime($request->start_at)) : null;
        $data['end_at'] = isset($request->end_at) ? date('Y-m-d H:i:s',strtotime($request->end_at)) : null;
        $data['cancellation_charge'] = isset($request->cancellation_charge) ? $request->cancellation_charge : 0;
        $data['cancellation_charge_amount'] = isset($request->cancellation_charge_amount) ? $request->cancellation_charge_amount : 0;

        $bookingdata = Booking::find($id);
        $paymentdata = Payment::where('booking_id',$id)->first();
        $user_wallet = Wallet::where('user_id', $bookingdata->customer_id)->first();
        $wallet_amount = $user_wallet->amount;
        if($request->type == 'service_addon'){
            if($request->has('service_addon') && $request->service_addon != null ){
                foreach($request->service_addon as $serviceaddon){
                    $get_addon = BookingServiceAddonMapping::where('id',$serviceaddon)->first();
                    $get_addon->status = 1;
                    $get_addon->update();
                }
                $message = __('messages.update_form',[ 'form' => __('messages.booking') ] );

                if($request->is('api/*')) {
                    return comman_message_response($message);
                }
            }
        }
        if($request->has('service_addon') && $request->service_addon != null ){
            foreach($request->service_addon as $serviceaddon){
                $get_addon = BookingServiceAddonMapping::where('id',$serviceaddon)->first();
                $get_addon->status = 1;
                $get_addon->update();
            }
        }
        if($data['status'] === 'hold'){
            if($bookingdata->start_at == null && $bookingdata->end_at == null){
                $duration_diff = $data['duration_diff'];
                $data['duration_diff'] = $duration_diff;
            }else{
                if($bookingdata->status == $data['status']){
                    $booking_start_date = $bookingdata->start_at;
                    $request_start_date = $data['start_at'];
                    if($request_start_date > $booking_start_date){
                        $msg = __('messages.already_in_status',[ 'status' => $data['status'] ] );
                        return comman_message_response($msg);
                    }
                }else{
                    $duration_diff = $bookingdata->duration_diff;
                    if($bookingdata->start_at != null && $bookingdata->end_at != null){
                        $new_diff = $data['duration_diff'];
                    }else{
                        $new_diff = $data['duration_diff'];
                    }
                    $data['duration_diff'] = $duration_diff + $new_diff;
                    $bookingdata['duration_diff'] = $data['duration_diff'];
                    $data['final_total_service_price'] = round($bookingdata->getServiceTotalPrice(),$digitafter_decimal_point);
                    $data['final_discount_amount'] = round($bookingdata->getDiscountValue(),$digitafter_decimal_point);
                    $data['final_coupon_discount_amount'] = round($bookingdata->getCouponDiscountValue(),$digitafter_decimal_point);
                    $subtotal = $bookingdata->getSubTotalValue() + $bookingdata->getServiceAddonValue();;
                    $data['final_sub_total'] = $subtotal;
                    $tax = round($bookingdata->getTaxesValue(),$digitafter_decimal_point);
                    $data['final_total_tax'] = $tax;
                        // without include extrachage tax caculation
                    $totalamount =   $subtotal + $tax;;
                    $data['total_amount'] =round($totalamount,$digitafter_decimal_point);
                }
            }
        }
        if($data['status'] === 'pending_approval'){
            $duration_diff = $bookingdata->duration_diff;
            $new_diff = $data['duration_diff'];

            $data['duration_diff'] = $duration_diff + $new_diff;
            $bookingdata['duration_diff'] = $data['duration_diff'];
            $data['final_total_service_price'] = round($bookingdata->getServiceTotalPrice(),$digitafter_decimal_point);
            $data['final_discount_amount'] = round($bookingdata->getDiscountValue(),$digitafter_decimal_point);
            $data['final_coupon_discount_amount'] = round($bookingdata->getCouponDiscountValue(),$digitafter_decimal_point);
            $subtotal = $bookingdata->getSubTotalValue() + $bookingdata->getServiceAddonValue();
            $data['final_sub_total'] = $subtotal;
            $tax = round($bookingdata->getTaxesValue(),$digitafter_decimal_point);
            $data['final_total_tax'] = $tax;
                // without include extrachage tax caculation
            $totalamount =   $subtotal + $tax;;
            $data['total_amount'] =round($totalamount,$digitafter_decimal_point);

        }
        if($bookingdata->status != $data['status']) {
            $activity_type = 'update_booking_status';
        }
        if($data['status'] == 'cancelled'){
            $activity_type = 'cancel_booking';
        }

        if($data['status'] == 'rejected'){
            if($bookingdata->handymanAdded()->count() > 0){
                $assigned_handyman_ids = $bookingdata->handymanAdded()->pluck('handyman_id')->toArray();
                $bookingdata->handymanAdded()->delete();
                $data['status'] = 'accept';
            }
        }
        if($data['status'] == 'pending'){
            if($bookingdata->handymanAdded()->count() > 0){
                $bookingdata->handymanAdded()->delete();
                $data['status'] = 'accept';
            }
        }

        if(($data['status'] == 'rejected' || $data['status'] == 'cancelled') && $data['payment_status'] =='advanced_paid'){
            $advance_paid_amount = $bookingdata->advance_paid_amount;
            $cancellation_charges = $data['cancellation_charge_amount'];


            if($cancellation_charges > 0 ){
                $user_wallet->amount = ($wallet_amount + $advance_paid_amount) - $cancellation_charges;
            }else{
                $user_wallet->amount = $wallet_amount + $advance_paid_amount;
            }

            $user_wallet->update();
            $paymentData = Payment::where('booking_id', $bookingdata->id)->first();
            $paymentData->payment_status = 'Advanced Refund';
            $paymentData->update();
            $activity_data = [
                'activity_type' => 'wallet_refund',
                'payment_status' => 'Advance Payment',
                'wallet' => $user_wallet,
                'booking_id'=> $id,
                'refund_amount'=> $advance_paid_amount,
            ];
            $this->sendNotification($activity_data);

        }
        $data['reason'] = isset($data['reason']) ? $data['reason'] : null;

        if($data['status'] == 'cancelled' && $data['cancellation_charge_amount'] > 0 && $data['payment_status'] !=='advanced_paid'){
            $cancellation_charges = $data['cancellation_charge_amount'];
            $user_wallet->amount = $wallet_amount - $cancellation_charges;
            $user_wallet->update();
            $activity_data = [
                'activity_type' => 'cancellation_charges',
                'wallet' => $user_wallet,
                'booking_id'=> $id,
                'paid_amount'=> $cancellation_charges,
            ];
            $this->sendNotification($activity_data);
        }
        $old_status = $bookingdata->status;
        if(!empty($request->extra_charges)){
            if($bookingdata->bookingExtraCharge()->count() > 0)
            {
                $bookingdata->bookingExtraCharge()->delete();
            }
            foreach($request->extra_charges as $extra) {
                $extra_charge = [
                    'title'   => $extra['title'],
                    'price'   => $extra['price'],
                    'qty'   => $extra['qty'],
                    'booking_id'   =>$bookingdata->id,
                ];
                $bookingdata->bookingExtraCharge()->insert($extra_charge);
            }
            $subtotal = $bookingdata->getSubTotalValue() + $bookingdata->getServiceAddonValue() + $bookingdata->getExtraChargeValue();

            // without include extrachage tax caculation
            $data['final_sub_total'] = $subtotal;
            $tax = $bookingdata->getTaxesValue();
            $data['final_total_tax'] = round($tax,$digitafter_decimal_point);
            $totalamount =   $subtotal + $tax;
            $data['total_amount'] =round($totalamount,$digitafter_decimal_point);

            // with include extracharge tax caculation
            // $totalamount =   $subtotal + $bookingdata->getExtraChargeValue() + $tax;
            // $data['total_amount'] =round($totalamount,2);
            // $data['final_total_tax'] = round($tax,2);
        }


        $bookingdata->update($data);
        if($bookingdata && $bookingdata->status === 'completed'){
            $this->addBookingCommission($bookingdata);
        }

        if($old_status != $data['status'] ){
            $bookingdata->old_status = $old_status;
            $activity_data = [
                'activity_type' => $activity_type,
                'booking_id' => $id,
                'booking' => $bookingdata,
            ];
            $this->sendNotification($activity_data);

        }

        if($bookingdata->payment_id != null){
            $payment_status = isset($data['payment_status']) ? $data['payment_status'] : 'pending';
            $paymentdata->update(['payment_status' => $payment_status]);
        }

        if($data['status'] == 'completed' && $data['payment_status'] == 'pending_by_admin'){
            $handyman = BookingHandymanMapping::where('booking_id',$bookingdata->id)->first();
            $user = User::where('id',$handyman->handyman_id)->first();
            $payment_history = [
                'payment_id' => $paymentdata->id,
                'booking_id' => $paymentdata->booking_id,
                'type' => $paymentdata->payment_type,
                'sender_id' => $bookingdata->customer_id,
                'receiver_id' => $handyman->handyman_id,
                'total_amount' => $paymentdata->total_amount,
                'datetime' => date('Y-m-d H:i:s'),
                'text' =>  __('messages.payment_transfer',['from' => get_user_name($bookingdata->customer_id),'to' => get_user_name($handyman->handyman_id),
                'amount' => getPriceFormat((float)$paymentdata->total_amount) ]),
            ];
            if($user->user_type == 'provider'){
                $payment_history['status'] = config('constant.PAYMENT_HISTORY_STATUS.APPROVED_PROVIDER');
                $payment_history['action']= config('constant.PAYMENT_HISTORY_ACTION.PROVIDER_APPROVED_CASH');
            }else{
                $payment_history['status'] = config('constant.PAYMENT_HISTORY_STATUS.APPRVOED_HANDYMAN');
                $payment_history['action'] = config('constant.PAYMENT_HISTORY_ACTION.HANDYMAN_APPROVED_CASH');
            }
            if(!empty($paymentdata->txn_id)){
                $payment_history['txn_id'] =$paymentdata->txn_id;
            }
            if(!empty($paymentdata->other_transaction_detail)){
                $payment_history['other_transaction_detail'] =$paymentdata->other_transaction_detail;
            }
           $res =  PaymentHistory::create($payment_history);
           $res->parent_id = $res->id;
           $res->update();
        }

        if($data['status'] == 'accept'){
            //  dd($bookingdata);
           $frequence = $bookingdata['frequency'];  
              
               $datePreviousSession = Carbon::parse($bookingdata['date']);
               $dateProchaine ;  //on recupre la date prochaine de la session
       
               switch ($frequence) {
                   case 'semaine':
                       $dateProchaine = $datePreviousSession->copy()->addWeeks(1);
                   break;
       
                   case 'deux_semaines': 
                       $dateProchaine = $datePreviousSession->copy()->addWeeks(2);
                   break;
       
                   case 'mois': 
                       $dateProchaine = $datePreviousSession->copy()->addWeeks(5);
       
                   break;
               } 
              
              $otherController = new BookingClr();
                $requestData = [
                  "id" => 0,
                  "category_id" => $bookingdata['category_id'],
                  "provider_id" => $bookingdata['provider_id'],
                  "price" => $bookingdata['price'],
                  "quantity" => $bookingdata['quantity'],
                  "type" => $bookingdata['type'],
                  "date" => Carbon::parse($dateProchaine),
                  "booking_slot" => $bookingdata['booking_slot'],
                  "booking_day" => $bookingdata['booking_day'],
                  "is_slot" => $bookingdata['is_slot'],
                  "subcategory_name" => $bookingdata['subcategory_name'],
                  "discount" => $bookingdata['discount'],
                  "duration" => $bookingdata['duration'],
                  "status" => "waiting",
                  "description" => $bookingdata['description'],
                  "is_featured" => $bookingdata['is_featured'],
                  "frequency" => $bookingdata['frequency'],
                  "have_animals" => $bookingdata['have_animals'],
                  "hours_availables" => json_decode($bookingdata['hours_availables'],true),
                  "provider_name" => $bookingdata['provider_name'],
                  "category_name" => $bookingdata['category_name'],
                  "attachments" => $bookingdata['attachments'],
                  "total_review" => $bookingdata['total_review'],
                  "total_rating" => $bookingdata['total_rating'],
                  "is_favourite" => $bookingdata['is_favourite'],
                  "city_id" => $bookingdata['city_id'],
                  "provider_image" => $bookingdata['provider_image'],
                  "service_address_mapping" => $bookingdata['service_address_mapping'],
                  "booking_slots" => $bookingdata['booking_slots'],
                  "created_at" => $bookingdata['created_at'],
                  "customer_name" => $bookingdata['customer_name'],
                  "customer_id" => $bookingdata['customer_id'],
                  "service_attachments" => $bookingdata['service_attachments'],
                  "service_id" => $bookingdata['service_id'],
                  "user_id" => $bookingdata['user_id'],
                  "servicePackage" => $bookingdata['servicePackage'],
                  "isEnableAdvancePayment" => $bookingdata['isEnableAdvancePayment'],
                  "advancePaymentPercentage" => $bookingdata['advancePaymentPercentage'],
                  "advancePaymentAmount" => $bookingdata['advancePaymentAmount'],
                  "attachments_array" => $bookingdata['attachments_array'],
                  "visit_type" => $bookingdata['visit_type'],
                  "coupon_id" => $bookingdata['coupon_id']];
  
                $request = Request::create('/store', 'POST', $requestData);
                $otherController->store($request);
                $message = 'accept prochaine session cree automatique';
          }
        $message = __('messages.update_form',[ 'form' => __('messages.booking') ] );

        if($request->is('api/*')) {
            return comman_message_response($message);
		}
    }

    public function saveHandymanRating(Request $request)
    {
        $user = auth()->user();
        $rating_data = $request->all();
        $rating_data['customer_id'] = $user->id;
        $result = HandymanRating::updateOrCreate(['id' => $request->id], $rating_data);

        $message = __('messages.update_form',[ 'form' => __('messages.rating') ] );
		if($result->wasRecentlyCreated){
			$message = __('messages.save_form',[ 'form' => __('messages.rating') ] );
		}

        return comman_message_response($message);
    }
    public function frequencesToutes(Request $request){
        // Validation des paramètres
         
         // Récupération des paramètres
         //reinit pour tests
       $Currentdate  =Carbon::now(); 
         // a apartir de date debut session et a partir de current date 
         // IMPORTANT on n'affiche pas les anciens sessions precedente
         
         $dateDebutSession = Carbon::create(2024, 11, 25);  
         $frequence = "semaine";
     
   
  
         // Calcul des prochaines 5 dates en fonction de la fréquence
         $dates = [];
 
         // Déterminer la fréquence
         switch ($frequence) {
             case 'semaine':
                   // Vérifier que la date de début n'est pas dans le futur
                   if ($dateDebutSession > $Currentdate) {
                     $nombreDeWeeks =  0; // Pas de session passée si la date de début est future
                 }
 
                 // Calcul du nombre de mois
                 $nombreDeWeeks = $dateDebutSession->diffInWeeks($Currentdate);
                 for ($i = 0; $i < ($nombreDeWeeks)+6; $i++) {   
                     $date = $dateDebutSession->copy()->addWeeks($i );
             
                     // Ajouter uniquement les dates futures (supérieures à la date actuelle)
                     if ($date->greaterThan($Currentdate) && count($dates) < 5) {
                         $dates[] = $date->format('l d-m-Y');
                     }
                 }
 
             case 'deux_semaines':
                  
                 // Vérifier que la date de début n'est pas dans le futur
                 if ($dateDebutSession > $Currentdate) {
                     $nombreDeWeeks =  0; // Pas de session passée si la date de début est future
                 }
 
                 // Calcul du nombre de mois
                 $nombreDeWeeks = $dateDebutSession->diffInWeeks($Currentdate);
                 for ($i = 0; $i < ($nombreDeWeeks*2)+6; $i++) {   
                     // Ajouter le nombre de mois à la date de début de session
                     $date = $dateDebutSession->copy()->addWeeks($i*2);
             
                     // Ajouter uniquement les dates futures (supérieures à la date actuelle)
                     if ($date->greaterThan($Currentdate) && count($dates) < 5) {
                         $dates[] = $date->format('l d-m-Y');
                     }
                 }
                 break;
 
             case 'mois': 
                 // Vérifier que la date de début n'est pas dans le futur
                 if ($dateDebutSession > $Currentdate) {
                     $nombreDeMois =  0; // Pas de session passée si la date de début est future
                 }
 
                 // Calcul du nombre de mois
                 $nombreDeMois = $dateDebutSession->diffInMonths($Currentdate);
                 for ($i = 0; $i < $nombreDeMois+6; $i++) {  
                     $date = $dateDebutSession->copy()->addWeeks($i*5);
             
                     // Ajouter uniquement les dates futures (supérieures à la date actuelle)
                     if ($date->greaterThan($Currentdate) && count($dates) < 5) {
                         $dates[] = $date->format('l d-m-Y');
                     }
                 }
                 break;
 
             default:
                 return response()->json(['error' => 'Fréquence invalide'], 400);
         }
 
         return response()->json(['dates' => $dates]);
 
     }
 
 
     public function frequences(Request $request){
     
         $frequence = "semaine";
         // Calcul des prochaine 1 date en fonction de la fréquence
         $dateProchaine ; 
         $datePreviousSession = Carbon::create(2024, 11, 25);  
 
         switch ($frequence) {
             case 'semaine':
                 $dateProchaine = $datePreviousSession->copy()->addWeeks(1);
             break;
 
             case 'deux_semaines': 
                 $dateProchaine = $datePreviousSession->copy()->addWeeks(2);
             break;
 
             case 'mois': 
                 $dateProchaine = $datePreviousSession->copy()->addWeeks(5);
 
             break;
 
             default:  $dateProchaine = "error";
         }
       
         return response()->json(['dateProchaine' => $dateProchaine]);
  
 
 
     }
    public function getHandymanRatingList(Request $request){

        $handymanratings = HandymanRating::orderBy('id','desc');

        $per_page = config('constant.PER_PAGE_LIMIT');
        if($request->has('per_page') && !empty($request->per_page)){
            if(is_numeric($request->per_page)){
                $per_page = $request->per_page;
            }
            if($request->per_page === 'all'){
                $per_page = $handymanratings->count();
            }
        }

        $handymanratings = $handymanratings->paginate($per_page);
        $data = HandymanRatingResource::collection($handymanratings);

        return response ([
            'pagination' => [
                'total_ratings' => $data->total(),
                'per_page' => $data->perPage(5),
                'currentPage' => $data->currentPage(),
                'totalPages' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
                'next_page' => $data->nextPageUrl(),
                'previous_page' => $data->previousPageUrl(),
            ],
            'data' => $data,
        ]);
    }

    public function deleteHandymanRating(Request $request)
    {
        $user = auth()->user();

        $book_rating = HandymanRating::where('id',$request->id)->where('customer_id',$user->id)->delete();

        $message = __('messages.delete_form',[ 'form' => __('messages.rating') ] );

        return comman_message_response($message);
    }
    public function bookingRatingByCustomer(Request $request){
        $customer_review = null;
        if($request->customer_id != null){
            $customer_review = BookingRating::where('customer_id',$request->customer_id)->where('service_id',$request->service_id)->where('booking_id',$request->booking_id)->first();
            if (!empty($customer_review))
            {
                $customer_review = new BookingRatingResource($customer_review);
            }
        }
        return comman_custom_response($customer_review);

    }
    public function uploadServiceProof(Request $request){
        $booking = $request->all();
        $result = ServiceProof::create($booking);
        if($request->has('attachment_count')) {
            for($i = 0 ; $i < $request->attachment_count ; $i++){
                $attachment = "booking_attachment_".$i;
                if($request->$attachment != null){
                    $file[] = $request->$attachment;
                }
            }
            storeMediaFile($result,$file, 'booking_attachment');
        }
		if($result->wasRecentlyCreated){
			$message = __('messages.save_form',[ 'form' => __('messages.attachments') ] );
		}
        return comman_message_response($message);
    }

    public function getUserRatings(Request $request){
        $user = auth()->user();

        if(auth()->user() !== null){

            if(auth()->user()->hasRole('admin')){
                $ratings = BookingRating::orderBy('id','desc');
            }
            else{
                $ratings = BookingRating::where('customer_id', $user->id);
            }
        }


        $per_page = config('constant.PER_PAGE_LIMIT');
        if($request->has('per_page') && !empty($request->per_page)){
            if(is_numeric($request->per_page)){
                $per_page = $request->per_page;
            }
            if($request->per_page === 'all'){
                $per_page = $ratings->count();
            }
        }

        $ratings = $ratings->paginate($per_page);
        $data = BookingRatingResource::collection($ratings);

        return response ([
            'pagination' => [
                'total_ratings' => $data->total(),
                'per_page' => $data->perPage(5),
                'currentPage' => $data->currentPage(),
                'totalPages' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
                'next_page' => $data->nextPageUrl(),
                'previous_page' => $data->previousPageUrl(),
            ],
            'data' => $data,
        ]);
    }
    public function getRatingsList(Request $request){
        $type = $request->type;

        if ($type === 'user_service_rating') {
            $user = auth()->user();

            if(auth()->user() !== null){

                if(auth()->user()->hasRole('admin') || auth()->user()->hasRole('demo_admin')){
                    $ratings = BookingRating::orderBy('id','desc');
                }
                else{
                    $ratings = BookingRating::where('customer_id', $user->id)->orderBy('id','desc');
                }
            }
        }elseif ($type === 'handyman_rating') {
                $ratings = HandymanRating::orderBy('id','desc');
        }else {
                return response()->json(['message' => 'Invalid type parameter'], 400);
        }

        $per_page = config('constant.PER_PAGE_LIMIT');
        if($request->has('per_page') && !empty($request->per_page)){
            if(is_numeric($request->per_page)){
                $per_page = $request->per_page;
            }
            if($request->per_page === 'all'){
                $per_page = $ratings->count();
            }
        }

        $ratings = $ratings->paginate($per_page);
        $data = HandymanRatingResource::collection($ratings);

        return response ([
            'pagination' => [
                'total_ratings' => $data->total(),
                'per_page' => $data->perPage(5),
                'currentPage' => $data->currentPage(),
                'totalPages' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
                'next_page' => $data->nextPageUrl(),
                'previous_page' => $data->previousPageUrl(),
            ],
            'data' => $data,
        ]);
    }
    public function deleteRatingsList($id,Request $request){
        $type = $request->type;

        if(demoUserPermission()){
            $message = __('messages.demo.permission.denied');
            return comman_message_response($message);
        }
        if ($type === 'user_service_rating') {
            $bookingrating = BookingRating::find($id);
            $msg= __('messages.msg_fail_to_delete',['name' => __('messages.user_ratings')] );

            if($bookingrating != ''){
                $bookingrating->delete();
                $msg= __('messages.msg_deleted',['name' => __('messages.user_ratings')] );
            }
        }elseif ($type === 'handyman_rating') {
            $handymanrating = HandymanRating::find($id);
            $msg= __('messages.msg_fail_to_delete',['name' => __('messages.handyman_ratings')] );

            if($handymanrating != ''){
                $handymanrating->delete();
                $msg= __('messages.msg_deleted',['name' => __('messages.handyman_ratings')] );
            }
        }else {
            $msg = "Invalid type parameter";
            return comman_custom_response(['message'=> $msg, 'status' => false]);
        }

        return comman_custom_response(['message'=> $msg, 'status' => true]);
    }

    public function updateLocation(Request $request) {
        $bookingID = $request->input('booking_id');
        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');


        $data = [
            'booking_id' => $bookingID,
            'latitude' => $latitude,
            'longitude' => $longitude,

        ];
        $locations = LiveLocation::updateOrCreate(['booking_id' => $data['booking_id']], $data);
        $time_zone=getTimeZone();

        $datetime_in_timezone = Carbon::parse($locations->updated_at)->timezone($time_zone);

        $data['datetime'] = $datetime_in_timezone->toDateTimeString();

        $message = __('messages.location_update');
        return response()->json(['data' => $data, 'message' => $message], 200);
    }

    public function getLocation(Request $request){
        $bookingID = $request->input('booking_id');

        $latestLiveLocation = Cache::remember('latest_live_location_' . $bookingID, 30, function () use ($bookingID) {
            return LiveLocation::where('booking_id', $bookingID)
                ->latest()
                ->first();
        });
        if (!$latestLiveLocation) {
            return response()->json(['error' => 'Live location not found for this booking ID'], 404);
        }

        $time_zone=getTimeZone();

        $datetime_in_timezone = Carbon::parse($latestLiveLocation->updated_at)->timezone($time_zone);

        $datetime= $datetime_in_timezone->toDateTimeString();
        $data = [
            'latitude' => $latestLiveLocation->latitude,
            'longitude' => $latestLiveLocation->longitude,
            'datetime' =>  $datetime,
        ];

        $message = __('messages.location_update');
        return response()->json(['data' => $data, 'message' => $message], 200);

    }
}
