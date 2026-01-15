jQuery(document).ready(function ($) {
    // Sentence counter for new rows
    let sentenceCounter = $('#example-sentences-container .sentence-row').length;

    // Add sentence button
    $('#add-sentence-btn').on('click', function () {
        const index = sentenceCounter++;
        const template = `
            <div class="sentence-row" data-index="${index}">
                <div class="sentence-fields">
                    <div class="form-group">
                        <label for="sentence_${index}_text">Sentence</label>
                        <input type="text" id="sentence_${index}_text" name="sentences[${index}][aiwa_sentence]">
                    </div>
                    <div class="form-group">
                        <label for="sentence_${index}_trans">Translation</label>
                        <input type="text" id="sentence_${index}_trans" name="sentences[${index}][aiwa_s_translation]">
                    </div>
                    <div class="form-group">
                        <label for="sentence_${index}_trans_en">Translation (English)</label>
                        <input type="text" id="sentence_${index}_trans_en" name="sentences[${index}][aiwa_s_translation_english]">
                    </div>
                    <div class="form-group">
                        <label for="sentence_${index}_trans_fr">Translation (French)</label>
                        <input type="text" id="sentence_${index}_trans_fr" name="sentences[${index}][aiwa_s_translation_french]">
                    </div>
                </div>
                <button type="button" class="btn-text remove-sentence-btn" aria-label="Remove sentence">Remove</button>
            </div>
        `;
        $('#example-sentences-container').append(template);
    });

    // Remove sentence button
    $(document).on('click', '.remove-sentence-btn', function () {
        $(this).closest('.sentence-row').remove();
    });

    // Media uploader
    let mediaUploader;

    $(document).on('click', '.upload-media-btn', function (e) {
        e.preventDefault();

        const button = $(this);
        const fieldId = button.data('field');
        const mediaType = button.data('type');
        const container = button.closest('.media-upload-container');

        // If the uploader object has already been created, reopen the dialog
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }

        // Create the media uploader
        mediaUploader = wp.media({
            title: mediaType === 'image' ? 'Choose Image' : 'Choose Audio File',
            button: {
                text: 'Use this file',
            },
            library: {
                type: mediaType === 'image' ? 'image' : 'audio',
            },
            multiple: false,
        });

        // When a file is selected
        mediaUploader.on('select', function () {
            const attachment = mediaUploader.state().get('selection').first().toJSON();

            $('#' + fieldId).val(mediaType === 'image' ? attachment.id : attachment.url);

            if (mediaType === 'image') {
                container
                    .find('.image-preview-container')
                    .html('<img src="' + attachment.url + '" alt="Preview">');
                container.find('.remove-media-btn').attr('aria-label', 'Remove image');
            } else {
                container.find('.media-filename').text(attachment.filename);
                container.find('.remove-media-btn').attr('aria-label', 'Remove audio file');
            }

            container.find('.remove-media-btn').show();
        });

        // Open the uploader dialog
        mediaUploader.open();
    });

    // Remove media button
    $(document).on('click', '.remove-media-btn', function (e) {
        e.preventDefault();
        const container = $(this).closest('.media-upload-container');
        container.find('input[type="hidden"]').val('');
        container.find('.media-filename').text('');
        container.find('.image-preview-container').html('');
        $(this).hide();
    });

    // Synonym search with debounce
    let synonymSearchTimeout;
    $('#aiwa_synonyms_search').on('keyup', function () {
        clearTimeout(synonymSearchTimeout);
        const searchTerm = $(this).val();

        if (searchTerm.length < 2) {
            $('#synonym-results').empty();
            return;
        }

        synonymSearchTimeout = setTimeout(function () {
            $.ajax({
                url: aiwaDict.ajaxurl,
                type: 'POST',
                data: {
                    action: 'aiwa_dict_search_synonyms',
                    nonce: aiwaDict.nonce,
                    search: searchTerm,
                },
                success: function (response) {
                    if (response.success) {
                        displaySynonymResults(response.data.results);
                    }
                },
            });
        }, 300);
    });

    // Display synonym search results
    function displaySynonymResults(results) {
        const container = $('#synonym-results');
        container.empty();

        if (results.length === 0) {
            container.html('<div class="no-results">No results found</div>');
            return;
        }

        results.forEach(function (result) {
            const resultItem = $('<div class="synonym-result-item" role="option">')
                .text(result.title)
                .data('id', result.id)
                .on('click', function () {
                    addSynonym(result.id, result.title);
                })
                .on('keydown', function (e) {
                     if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        addSynonym(result.id, result.title);
                     }
                })
                .attr('tabindex', '0');
            container.append(resultItem);
        });
    }

    // Add synonym
    function addSynonym(id, title) {
        // Check if already added
        if ($('#selected-synonyms').find('[data-id="' + id + '"]').length > 0) {
            return;
        }

        const tag = $('<span class="synonym-tag" role="listitem">')
            .attr('data-id', id)
            .html(title + ' <button type="button" class="remove-synonym" aria-label="Remove synonym ' + title + '">×</button>');

        $('#selected-synonyms').append(tag);
        updateSynonymField();
        $('#synonym-results').empty();
        $('#aiwa_synonyms_search').val('');
    }

    // Remove synonym
    $(document).on('click', '.remove-synonym', function (e) {
        e.preventDefault();
        $(this).closest('.synonym-tag').remove();
        updateSynonymField();
    });

    // Update hidden synonym field
    function updateSynonymField() {
        const ids = [];
        $('#selected-synonyms .synonym-tag').each(function () {
            ids.push($(this).data('id'));
        });
        $('#aiwa_synonyms').val(ids.join(','));
    }

    // Form submission
    $('#sparx-dict-form').on('submit', function (e) {
        e.preventDefault();

        const form = $(this);
        const submitBtn = $('#submit-btn');
        const messageDiv = $('#form-message');

        // Disable submit button
        submitBtn.prop('disabled', true).text('Saving...');
        messageDiv.empty();

        // Serialize form data
        const formData = form.serialize();
        const entryId = form.data('entry-id');

        $.ajax({
            url: aiwaDict.ajaxurl,
            type: 'POST',
            data:
                formData +
                '&action=aiwa_dict_form_submit&nonce=' +
                aiwaDict.nonce +
                '&entry_id=' +
                entryId,
            success: function (response) {
                if (response.success) {
                    messageDiv.html(
                        '<div class="success-message">' + response.data.message + '</div>'
                    );

                    // Reset form if it was an add (not edit)
                    if (!entryId) {
                        form[0].reset();
                        $('#example-sentences-container').empty();
                        $('#selected-synonyms').empty();
                        $('.image-preview-container').empty();
                        $('.media-filename').empty();
                    }

                    // Show edit link
                    if (response.data.edit_url) {
                        messageDiv.append(
                            '<div class="info-message">View in admin: <a href="' +
                                response.data.edit_url +
                                '" target="_blank">Edit Post</a></div>'
                        );
                    }
                } else {
                    messageDiv.html(
                        '<div class="error-message">' + response.data.message + '</div>'
                    );
                }

                // Re-enable submit button
                submitBtn
                    .prop('disabled', false)
                    .text(entryId ? 'Save as New Draft' : 'Add Entry (Draft)');

                // Scroll to message
                $('html, body').animate(
                    {
                        scrollTop: messageDiv.offset().top - 100,
                    },
                    500
                );
            },
            error: function () {
                messageDiv.html(
                    '<div class="error-message">An error occurred. Please try again.</div>'
                );
                submitBtn
                    .prop('disabled', false)
                    .text(entryId ? 'Save as New Draft' : 'Add Entry (Draft)');
            },
        });
    });
});
