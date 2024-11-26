<?php

namespace App\Http\Resources\API;
use App\Models\Service;
use App\Models\PackageServiceMapping;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingPackageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $service_id = PackageServiceMapping::where('service_package_id',$this->service_package_id)->pluck('service_id');
        $services = json_decode($this->services);  // Decode JSON to array of services
        $resultServices = [];

        if ($services) {
            foreach ($services as $serviceItem) {
                // Assuming you have a Service model where you can fetch service details using the service_id
                $service = Service::find($serviceItem->service_id); // Fetch service details by service_id

                if ($service) {
                    // Prepare the result array with necessary details
                    $resultServices[] = [
                        'id' => $service->id,
                        'name' => $service->name,
                        'category_id' => $service->category_id,
                        'category_name'  => optional($service->category)->name,
                        'subcategory_id' => $service->subcategory_id,
                        'subcategory_name'  => optional($service->subcategory)->name,
                        'provider_id' => $service->provider_id,
                        'price' => $serviceItem->price,    
                        'attchments' => getAttachments($service->getMedia('service_attachment')), 
                        'attchments_array' => getAttachmentArray($service->getMedia('service_attachment'),null),
                    ];
                }
            }
        }

        return [
            'id'=> $this->id,
            'package_id' =>$this->service_package_id,
            'name'=> $this->name,
            'price'=> $this->price,
            'description'=> $this->description,
            'start_date'=> $this->start_at,
            'end_date'=> $this->end_at,
            'category_id'=> $this->category_id, // When package created based on Category wise//
            'subcategory_id'=> $this->subcategory_id, // When package created based on Category wise//
            'is_featured'=> $this->is_featured,
            'category_name'  => optional($this->category)->name,
            'subcategory_name'  => optional($this->subcategory)->name,
            'package_type' =>$this->package_type,
            'attchments' => getAttachments($this->package->getMedia('package_attachment')),
            'attchments_array' => getAttachmentArray($this->package->getMedia('package_attachment'),null),
            'services'    => $resultServices,
        ];
    }
}
