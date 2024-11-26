{{ html()->form('POST', route('saveEarningTypeSetting'))->attribute('enctype', 'multipart/form-data')->attribute('data-toggle', 'validator')->open() }}
{{ html()->hidden('id', $earningsetting->id ?? null )->attribute('placeholder', 'id')->class('form-control') }}
{{ html()->hidden('page', $page)->attribute('placeholder', 'id')->class('form-control') }}

<div class="row">
    <div class="col-lg-12">
        <div class="form-group">
            {{ html()->label(__('messages.earning_type_provider').' <span class="text-danger">*</span>', 'earning_type')->class('form-control-label') }}
            {{ html()->select('earning_type', ['commission' => __('messages.commission'),'subscription' => __('messages.subscription')])->class('form-control select2js')->required()->value($earningsetting->value ?? null) }}
        </div>
    </div>
    <div class="col-lg-12">
        <div class="form-group">
            <div class="col-md-offset-3 col-sm-12 ">
                {{ html()->submit(__('messages.save'))->class('btn btn-md btn-primary float-md-end') }}
            </div>
        </div>
    </div>
</div>
{{ html()->form()->close() }}
