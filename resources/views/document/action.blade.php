
<?php
    $auth_user= authSession();
?>
{{ html()->form('DELETE', route('document.destroy', $document->id))->attribute('data--submit', 'document'.$document->id)->open() }}
<div class="d-flex justify-content-end align-items-center ms-2">
    @if(!$document->trashed())
        <!-- @if($auth_user->can('document edit'))
        <a class="me-2" href="{{ route('document.create',['id' => $document->id]) }}" title="{{ __('messages.update_form_title',['form' => __('messages.document') ]) }}"><i class="fas fa-pen text-primary"></i></a>
        @endif -->

        @if($auth_user->can('document delete'))
        <a class="me-3" href="{{ route('document.destroy', $document->id) }}" data--submit="document{{$document->id}}" 
            data--confirmation='true' 
            data--ajax="true"
            data-datatable="reload"
            data-title="{{ __('messages.delete_form_title',['form'=>  __('messages.document') ]) }}"
            title="{{ __('messages.delete_form_title',['form'=>  __('messages.document') ]) }}"
            data-message='{{ __("messages.delete_msg") }}'>
            <i class="far fa-trash-alt text-danger"></i>
        </a>
        @endif
    @endif
    @if(auth()->user()->hasAnyRole(['admin']) && $document->trashed())
        <a href="{{ route('document.action',['id' => $document->id, 'type' => 'restore']) }}"
            title="{{ __('messages.restore_form_title',['form' => __('messages.document') ]) }}"
            data--submit="confirm_form"
            data--confirmation='true'
            data--ajax='true'
            data-title="{{ __('messages.restore_form_title',['form'=>  __('messages.document') ]) }}"
            data-message='{{ __("messages.restore_msg") }}'
            data-datatable="reload"
            class="me-2">
            <i class="fas fa-redo text-secondary"></i>
        </a>
        <a href="{{ route('document.action',['id' => $document->id, 'type' => 'forcedelete']) }}"
            title="{{ __('messages.forcedelete_form_title',['form' => __('messages.document') ]) }}"
            data--submit="confirm_form"
            data--confirmation='true'
            data--ajax='true'
            data-title="{{ __('messages.forcedelete_form_title',['form'=>  __('messages.document') ]) }}"
            data-message='{{ __("messages.forcedelete_msg") }}'
            data-datatable="reload"
            class="me-2">
            <i class="far fa-trash-alt text-danger"></i>
        </a>
    @endif
{{ html()->form()->close() }}