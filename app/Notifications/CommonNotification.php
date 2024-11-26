<?php

namespace App\Notifications;

use App\Broadcasting\CustomWebhook;
// use App\Broadcasting\OneSingleChannel;
use App\Mail\MailMailableSend;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use App\Models\NotificationTemplate;
use App\Broadcasting\FcmChannel;
use App\Models\MailTemplateContentMapping;
use App\Models\MailTemplates;
use App\Models\NotificationTemplateContentMapping;
use Illuminate\Support\Facades\Log;

class CommonNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $type;

    public $data;

    public $subject;

    public $notification;

    public $notification_message;

    public $notification_link;

    public $appData;

    public $custom_webhook;


    /**
     * Create a new notification instance.
     */
    public function __construct($type, $data)
    {
        $this->type = $type;
        $this->data = $data;

        $userType = $data['user_type'];
        $notifications = NotificationTemplate::where('type', $this->type)
            ->with('defaultNotificationTemplateMap')
            ->first();
        $notify_data = NotificationTemplateContentMapping::where('template_id', $notifications->id)->get();
        $templateData = $notify_data->where('user_type', $userType)->first();
        $templateDetail = $templateData->template_detail ?? null;
        foreach ($this->data as $key => $value) {
            $templateDetail = str_replace('[[ ' . $key . ' ]]', $this->data[$key], $templateDetail);
        }
        $this->data['type'] = $templateData->subject ?? 'None';
        $this->data['message'] = $templateDetail ?? __('messages.default_notification_body');
        $this->appData = $notifications->channels;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        $notificationSettings = $this->appData;
        $notification_settings = [];
        $notification_access = isset($notificationSettings[$this->type]) ? $notificationSettings[$this->type] : [];
        if (isset($notificationSettings)) {
            foreach ($notificationSettings as $key => $notification) {
                if ($notification) {

                    switch ($key) {

                        case 'PUSH_NOTIFICATION':

                            Log::info($notification_settings);
                            array_push($notification_settings, FcmChannel::class);

                            break;

                        case 'IS_CUSTOM_WEBHOOK':
                            array_push($notification_settings, CustomWebhook::class);

                            break;

                        case 'IS_MAIL':

                            Log::info($notification_settings);
                            array_push($notification_settings, 'mail');

                            break;
                    }
                }
            }
        }

        return array_merge($notification_settings, ['database']);
    }



    /**
     * Get mail notification
     *
     * @param  mixed  $notifiable
     * @return MailMailableSend
     */
    public function toMail($notifiable)
    {
        $userType = $this->data['user_type'];
        $email = isset($notifiable->email) ? $notifiable->email : $notifiable->routes['mail'];

        $mail = MailTemplates::where('type', $this->type)
            ->with('defaultMailTemplateMap')
            ->first();

        $notify_data = MailTemplateContentMapping::where('template_id', $mail->id)->get();
        $templateData = $notify_data->where('user_type', $userType)->first();
        $this->subject = $templateData->subject;
        $this->data['type'] = $templateData->subject ?? null;
        $this->data['message'] = $templateData->template_detail ?? __('messages.default_notification_body');
        return (new MailMailableSend($this->notification, $this->data, $this->type))->to($email)
            ->bcc(isset($this->notification->bcc) ? json_decode($this->notification->bcc) : [])
            ->cc(isset($this->notification->cc) ? json_decode($this->notification->cc) : [])
            ->subject($this->subject);
    }


    public function toFcm($notifiable)
    {
        $msg = strip_tags($this->data['message']);
        if (! isset($msg) && $msg == '') {
            $msg = __('message.notification_body');
        }
        $type = 'booking';
        if (isset($this->data['type']) && $this->data['type'] !== '') {
            $type = $this->data['type'];
        }

        $heading =  $this->data['type'] ?? '';
       
        $additionalData = json_encode($this->data);
        return fcm([
            "message" => [
                "topic" => 'user_'.$notifiable->id,
                "notification" => [
                    "title" => $heading,
                    "body" => $msg,
                ],
                "data" => [                    
                    "sound"=>"default", 
                    "story_id" => "story_12345",
                    "type" => $type,
                    "additional_data" => $additionalData,
                    "click_action"=> "FLUTTER_NOTIFICATION_CLICK",
                ],
                "android" => [
                    "priority" => "high",
                    "notification" => [                        
                        "click_action"=> "FLUTTER_NOTIFICATION_CLICK",
                    ],
                ],
                "apns" => [
                    "payload" => [
                        "aps" => [
                            "category" => "NEW_MESSAGE_CATEGORY",
                        ],
                    ],
                ],
            ],
        ]);
    }



    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return $this->data;
    }
}
