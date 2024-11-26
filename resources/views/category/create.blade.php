<x-master-layout>
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <div class="card card-block card-stretch">
                    <div class="card-body p-0">
                        <div class="d-flex justify-content-between align-items-center p-3 flex-wrap gap-3">
                            <h5 class="fw-bold">{{ $pageTitle ?? trans('messages.list') }}</h5>
                            @if($auth_user->can('category list'))
                            <a href="{{ route('category.index') }}" class=" float-end btn btn-sm btn-primary"><i class="fa fa-angle-double-left"></i> {{ __('messages.back') }}</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        {{ html()->form('POST', route('category.store'))
                        ->attribute('enctype', 'multipart/form-data')
                        ->attribute('data-toggle', 'validator')
                        ->id('category-form')
                        ->open()
                    }}
                    {{ html()->hidden('id', $categorydata->id ?? null) }}
                        <div class="row">
                            <div class="form-group col-md-4">
                                {!! html()->label(__('messages.name') . ' <span class="text-danger">*</span>')
                                ->for('name')
                                ->class('form-control-label') !!}

                            {{html()->text('name', $categorydata->name)
                            ->placeholder(__('messages.name'))
                            ->class('form-control')
                            ->attribute('required', true)
                            ->attribute('title', 'Please enter alphabetic characters and spaces only')}}

                                <small class="help-block with-errors text-danger"></small>
                            </div>

                            <div class="form-group col-md-4">
                                {{html()->label(trans('messages.status') . ' <span class="text-danger">*</span>')
                                ->for('status')
                                ->class('form-control-label') }}

                            {{ html()->select('status', [
                                '1' => __('messages.active'),
                                '0' => __('messages.inactive')
                            ], old('status'))
                            ->id('role')
                            ->class('form-control select2js')
                            ->attribute('required', true) }}
                            </div>

                            <div class="form-group col-md-4">
                                <label class="form-control-label" for="category_image">{{ __('messages.image') }} <span class="text-danger">*</span></label>
                                <div class="custom-file">
                                    <input type="file" name="category_image" class="custom-file-input" onchange="previewImage(event)" accept="image/*" required>
                                    @if($categorydata && getMediaFileExit($categorydata, 'category_image'))
                                    <label class="custom-file-label upload-label">{{ $categorydata->getFirstMedia('category_image')->file_name }}</label>
                                    @else
                                    <label class="custom-file-label upload-label">{{ __('messages.choose_file', ['file' => __('messages.image')]) }}</label>
                                    @endif
                                </div>
                            </div>

                            <div class="col-md-2 mb-2">
                                <div class="image-preview-container position-relative">
                                    <img id="category_image_preview" src="{{ getMediaFileExit($categorydata, 'category_image') ? getSingleMedia($categorydata, 'category_image') : '' }}" alt="Image preview" class="attachment-image mt-1" style="width: 150px; {{ getMediaFileExit($categorydata, 'category_image') ? '' : 'display: none;' }}">
                                    <a class="text-danger remove-file d-none" id="removeButton" onclick="removeImage(event, '{{ route('remove.file', ['id' => $categorydata->id, 'type' => 'category_image']) }}')">
                                        <i class="ri-close-circle-line"></i>
                                    </a>
                                </div>
                            </div>

                            <div class="form-group col-md-12">
                                {{ html()->label(trans('messages.description' ))
                                ->for('description')
                                ->class('form-control-label')}}

                            {!! html()->textarea('description', $categorydata->description)
                                ->class('form-control textarea')
                                ->rows(3)
                                ->placeholder(__('messages.description')) !!}

                            </div>
                        </div>

                        <div class="row">
                            <div class="form-group col-md-6">
                                <div class="custom-control custom-switch custom-control-inline">
                                    {{html()->checkbox('is_featured', $categorydata->is_featured)
                                    ->class('custom-control-input')
                                    ->id('is_featured')}}
                                    <label class="custom-control-label" for="is_featured">{{ __('messages.set_as_featured') }}</label>
                                </div>
                            </div>
                        </div>

                        {{ html()->submit(trans('messages.save'))
                        ->class('btn btn-md btn-primary float-end')
                        ->id('saveButton') }}
                     {{ html()->form()->close() }}

                    </div>
                </div>
            </div>
        </div>
    </div>

    @section('bottom_script')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script type="text/javascript">
        function previewImage(event) {
            const preview = document.getElementById('category_image_preview');
            const fileLabel = document.querySelector('.custom-file-label');
            const saveButton = document.getElementById('saveButton');
            const removeButton = document.getElementById('removeButton');

            preview.src = URL.createObjectURL(event.target.files[0]);
            preview.style.display = 'block'; // Show the image
            fileLabel.textContent = event.target.files[0].name; // Update label with the file name

            // Show the remove button and enable the save button
            $('#removeButton').removeClass('d-none');
            saveButton.disabled = false;
        }

        function removeImage(event, removeUrl) {
            event.preventDefault(); // Prevent default link behavior

            // SweetAlert confirmation
            Swal.fire({
                title: 'Are you sure?',
                text: "Do you want to remove the category image?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, remove it!',
                cancelButtonText: 'No, keep it'
            }).then((result) => {
                if (result.isConfirmed) {
                    const preview = document.getElementById('category_image_preview');
                    const fileLabel = document.querySelector('.custom-file-label');
                    const saveButton = document.getElementById('saveButton');
                    const removeButton = document.getElementById('removeButton');

                    // AJAX request to remove the media file
                    $.ajax({
                        url: removeUrl,
                        type: 'POST',
                        success: function(result) {
                            // Handle success
                            preview.src = '';
                            preview.style.display = 'none';
                            document.querySelector('input[name="category_image"]').value = ''; // Clear the file input
                            fileLabel.textContent = '{{ __('messages.choose_file', ['file' => __('messages.image')]) }}'; // Reset the label text
                            saveButton.disabled = true; // Disable the save button
                            $('#removeButton').addClass('d-none'); // Hide the remove button

                            // Optionally show a success message
                            Swal.fire(
                                'Deleted!',
                                'Your category image has been removed.',
                                'success'
                            );
                        },
                        error: function(xhr, status, error) {
                            console.error('Error removing media file:', error);
                        }
                    });
                }
            });
        }

        function removeLocalImage() {
            const preview = document.getElementById('category_image_preview');
            const fileLabel = document.querySelector('.custom-file-label');
            const saveButton = document.getElementById('saveButton');
            const removeButton = document.getElementById('removeButton');

            // Check if the image exists before removing
            if (preview.src) {
                preview.src = '';
                preview.style.display = 'none';
                document.querySelector('input[name="category_image"]').value = ''; // Clear the file input
                fileLabel.textContent = '{{ __('messages.choose_file', ['file' => __('messages.image')]) }}'; // Reset the label text
                saveButton.disabled = true; // Disable the save button
                $('#removeButton').addClass('d-none'); // Hide the remove button
            }
        }

        function removeImage(event, removeUrl) {
    event.preventDefault(); // Prevent default link behavior

    // SweetAlert confirmation
    Swal.fire({
        title: 'Are you sure?',
        text: "Do you want to remove the category image?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, remove it!',
        cancelButtonText: 'No, keep it'
    }).then((result) => {
        if (result.isConfirmed) {
            const preview = document.getElementById('category_image_preview');
            const fileLabel = document.querySelector('.custom-file-label');
            const saveButton = document.getElementById('saveButton');
            const removeButton = document.querySelector('.remove-button'); // Get the remove button

            // AJAX request to remove the media file
            $.ajax({
                url: removeUrl,
                type: 'POST',
                success: function(result) {
                    // Handle success, e.g., show a success message
                    preview.src = '';
                    preview.style.display = 'none';
                    document.querySelector('input[name="category_image"]').value = ''; // Clear the file input
                    fileLabel.textContent = '{{ __('messages.choose_file', ['file' => __('messages.image')]) }}'; // Reset the label text
                    saveButton.disabled = true; // Disable the save button
                    // removeButton.style.display = 'none'; // Hide the remove button
                    $('#removeButton').addClass('d-none');
                    // Optionally show a success message
                    Swal.fire(
                        'Deleted!',
                        'Your category image has been removed.',
                        'success'
                    );
                },
                error: function(xhr, status, error) {
                    console.error('Error removing media file:', error);
                }
            });
        }
    });
}





function removeLocalImage(event) {
    const preview = document.getElementById('category_image_preview');
    const fileLabel = document.querySelector('.custom-file-label');
    const saveButton = document.getElementById('saveButton');
    const removeButton = document.querySelector('.remove-button'); // Get the remove button

    preview.src = '';
    preview.style.display = 'none';
    document.querySelector('input[name="category_image"]').value = ''; // Clear the file input
    fileLabel.textContent = '{{ __('messages.choose_file', ['file' => __('messages.image')]) }}'; // Reset the label text

    // Disable save button if image is required and not present
    saveButton.disabled = true;

    // Hide the remove button
    $('#removeButton').addClass('d-none');
}



        document.addEventListener('DOMContentLoaded', function() {
            checkImage();
        });

        function checkImage() {
            var id = @json($categorydata->id);
            var route = "{{ route('check-image', ':id') }}";
            route = route.replace(':id', id);
            var type = 'category';

            $.ajax({
                url: route,
                type: 'GET',
                data: {
                    type: type,
                },
                success: function(result) {
                    var attachments = result.results;
                    var attachmentsCount = Object.keys(attachments).length;
                    if (attachmentsCount == 0) {
                        $('input[name="category_image"]').attr('required', 'required');
                        document.getElementById('saveButton').disabled = true; // Disable button initially
                    } else {
                        $('input[name="category_image"]').removeAttr('required');
                        document.getElementById('saveButton').disabled = false; // Enable if there's an image
                        $('#removeButton').removeClass('d-none');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                }
            });
        }
    </script>
    @endsection
</x-master-layout>
