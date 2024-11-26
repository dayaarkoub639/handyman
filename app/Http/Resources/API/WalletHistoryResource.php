<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Wallet;
class WalletHistoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id'                => $this->id,
            'datetime'           => $this->datetime,
            'activity_type'             => $this->activity_type,
            'activity_message'        => $this->activity_message,
            'activity_data'            => $this->activity_data,     
            'user_image' => optional($this->providers)->login_type != null ?  optional($this->providers)->social_image : getSingleMedia(optional($this->providers), 'profile_image',null),   
        ];
    }
}