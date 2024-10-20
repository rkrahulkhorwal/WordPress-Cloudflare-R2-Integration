jQuery(document).ready(function($) {
    $('#r2-upload-form').on('submit', function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        formData.append('action', 'upload_to_r2');
        formData.append('security', wpCloudflareR2Ajax.nonce);

        var $progressBar = $('#r2-upload-progress');
        var $progressBarFill = $progressBar.find('.progress-bar-fill');
        var $progressText = $progressBar.find('.progress-text');
        var $result = $('#r2-upload-result');

        $.ajax({
            url: wpCloudflareR2Ajax.ajaxurl,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            beforeSend: function() {
                $progressBar.removeClass('hidden');
                $progressBarFill.width('0%');
                $progressText.text('Uploading: 0%');
                $result.removeClass('success error').empty();
            },
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener("progress", function(evt) {
                    if (evt.lengthComputable) {
                        var percentComplete = evt.loaded / evt.total;
                        percentComplete = parseInt(percentComplete * 100);
                        $progressBarFill.width(percentComplete + '%');
                        $progressText.text('Uploading: ' + percentComplete + '%');
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                if (response.success) {
                    $result.addClass('success').text(response.data);
                } else {
                    $result.addClass('error').text('Upload failed: ' + response.data);
                }
            },
            error: function() {
                $result.addClass('error').text('Upload failed. Please try again.');
            },
            complete: function() {
                $progressBar.addClass('hidden');
            }
        });
    });

    $('#r2-file-input').on('change', function() {
        var fileName = $(this).val().split('\\').pop();
        $(this).siblings('.file-input-label').text(fileName || 'Choose File');
    });
});