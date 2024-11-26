@extends('landing-page.layouts.default')


@section('content')
<div class="my-5 privacy-policy">
    <h4 class="text-center text-capitalize fw-bold my-5">{{__('messages.terms_condition')}}</h4>
    <div class="container">
        {!! $term_condition->value ?? null !!}
    </div>
</div>

<script>
   tinymce.init({
      selector: '.container',
      height: 500,
      plugins: 'code',
      toolbar: 'undo redo | formatselect | bold italic | alignleft aligncenter alignright alignjustify | code',
   });
</script>

@endsection
