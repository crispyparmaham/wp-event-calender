/**
 * Training Calendar - Anmeldeformular JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        
        // Event-Auswahl: Details laden
        $(document).on('change', '.tc-event-select', function() {
            var eventId = $(this).val();
            var form = $(this).closest('.tc-registration-form');
            var detailsDiv = form.find('.tc-event-details');
            var datePicker = form.find('.tc-form-group .tc-form-group-date-picker, [id*="-date-picker"]');
            var submitBtn = form.find('.tc-submit-btn');

            if (!eventId) {
                detailsDiv.hide();
                if (datePicker.length) datePicker.hide();
                submitBtn.prop('disabled', false).removeClass('tc-btn-disabled');
                return;
            }

            $.ajax({
                type: 'POST',
                url: tcRegistration.ajaxUrl,
                data: {
                    action: 'tc_get_event_details',
                    nonce: tcRegistration.nonce,
                    event_id: eventId
                },
                beforeSend: function() {
                    detailsDiv.css('opacity', '0.6');
                },
                success: function(response) {
                    if (response.success && response.data) {
                        var data = response.data;
                        detailsDiv.find('.tc-detail-leadership').text(data.leadership);
                        detailsDiv.find('.tc-detail-location').text(data.location);
                        
                        var dateStr = data.start_date;
                        if (data.start_time) {
                            dateStr += ' um ' + data.start_time;
                        }
                        detailsDiv.find('.tc-detail-date').text(dateStr);
                        
                        detailsDiv.find('.tc-detail-item-capacity').remove();
                        
                        if (data.track_participants && data.max_participants > 0) {
                            var capacityText = data.current_registrations + '/' + data.max_participants + ' Teilnehmer';
                            var capacityClass = data.is_full ? 'tc-full' : 'tc-available';
                            var capacityHtml = '<div class="tc-detail-item tc-detail-item-capacity">' +
                                '<span class="tc-detail-label">Kapazität:</span>' +
                                '<span class="tc-detail-capacity ' + capacityClass + '">' + capacityText + '</span>' +
                                '</div>';
                            detailsDiv.find('.tc-detail-date').closest('.tc-detail-item').after(capacityHtml);
                        }
                        
                        detailsDiv.show();
                        detailsDiv.css('opacity', '1');

                        if (data.is_full) {
                            submitBtn.prop('disabled', true).addClass('tc-btn-disabled');
                            submitBtn.find('.tc-btn-text').text('Ausgebucht');
                        } else {
                            submitBtn.prop('disabled', false).removeClass('tc-btn-disabled');
                            submitBtn.find('.tc-btn-text').text('Anmeldung absenden');
                        }

                        var datePickerField = form.find('[id*="-date-picker"]');
                        var showDatePicker = (data.is_multiday || data.is_recurring) && data.dates.length > 0;

                        if (showDatePicker && datePickerField.length) {
                            var selectField = datePickerField.find('select');
                            var labelText = data.is_recurring ? 'Wählen Sie einen Termin' : 'Wählen Sie ein Datum';
                            
                            datePickerField.find('.tc-date-picker-label').text(labelText + ' ');
                            datePickerField.find('.tc-required').text('*');
                            
                            selectField.find('option:not(:first)').remove();

                            $.each(data.dates, function(i, date) {
                                var dateObj = new Date(date + 'T00:00:00');
                                var formatted = dateObj.toLocaleDateString('de-DE', {
                                    weekday: 'long',
                                    year: 'numeric',
                                    month: 'long',
                                    day: 'numeric'
                                });
                                selectField.append('<option value="' + date + '">' + formatted + '</option>');
                            });

                            datePickerField.show();
                        } else {
                            if (datePickerField.length) {
                                datePickerField.hide();
                            }
                        }
                    }
                },
                error: function() {
                    console.error('Fehler beim laden der Event-Details');
                    detailsDiv.css('opacity', '1');
                }
            });
        });

        // Formulareinsendung
        $(document).on('submit', '.tc-registration-form', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $submitBtn = $form.find('.tc-submit-btn');
            var $btnText = $submitBtn.find('.tc-btn-text');
            var $btnLoader = $submitBtn.find('.tc-btn-loader');
            var $messages = $form.find('.tc-form-messages');

            if (!$form[0].checkValidity()) {
                $form[0].reportValidity();
                return false;
            }

            var formData = {
                action: $form.find('input[name="action"]').val(),
                nonce: $form.find('input[name="nonce"]').val(),
                firstname: $form.find('input[name="firstname"]').val().trim(),
                lastname: $form.find('input[name="lastname"]').val().trim(),
                email: $form.find('input[name="email"]').val().trim(),
                phone: $form.find('input[name="phone"]').val().trim(),
                address: $form.find('input[name="address"]').val().trim(),
                zip: $form.find('input[name="zip"]').val().trim(),
                city: $form.find('input[name="city"]').val().trim(),
                event_id: $form.find('input[name="event_id"], select[name="event_id"]').val(),
                event_date: $form.find('select[name="event_date"]').val() || '',
                notes: $form.find('textarea[name="notes"]').val().trim()
            };

            $form.addClass('tc-form-submitting');
            $submitBtn.prop('disabled', true);
            $btnText.hide();
            $btnLoader.show();
            $messages.hide().removeClass('tc-success tc-error tc-warning');

            $.ajax({
                type: 'POST',
                url: tcRegistration.ajaxUrl,
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $messages
                            .addClass('tc-success')
                            .html(response.data.message || 'Anmeldung erfolgreich!')
                            .show();

                        $form[0].reset();
                        $form.find('.tc-event-details').hide();
                        $form.addClass('tc-form-submitted');

                        setTimeout(function() {
                            $form.removeClass('tc-form-submitted');
                        }, 3000);
                    } else {
                        showFormError($form, response.data.message || 'Es gab einen Fehler beim absenden.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    showFormError($form, 'Es gab einen Fehler beim absenden. Bitte versuchen Sie es später erneut.');
                },
                complete: function() {
                    $form.removeClass('tc-form-submitting');
                    $submitBtn.prop('disabled', false);
                    $btnText.show();
                    $btnLoader.hide();
                }
            });

            return false;
        });

        function showFormError($form, message) {
            var $messages = $form.find('.tc-form-messages');
            $messages
                .addClass('tc-error')
                .html(message)
                .show();

            $('html, body').animate({
                scrollTop: $messages.offset().top - 100
            }, 300);
        }

        $(document).on('blur change', '.tc-form-control', function() {
            var $input = $(this);
            var $group = $input.closest('.tc-form-group');
            var errorMsg = $group.find('.tc-field-error-message');

            if (!$input[0].checkValidity()) {
                if (errorMsg.length === 0) {
                    $input.after('<span class="tc-field-error-message"></span>');
                    errorMsg = $group.find('.tc-field-error-message');
                }
                
                var message = 'Dieses Feld ist erforderlich';
                if ($input.attr('type') === 'email' && $input.val()) {
                    message = 'Bitte geben Sie eine gültige E-Mail-Adresse ein';
                }
                
                errorMsg.text(message);
                $group.addClass('tc-field-error');
            } else {
                $group.removeClass('tc-field-error');
                errorMsg.remove();
            }
        });

        $(document).on('input', '.tc-form-control', function() {
            var $group = $(this).closest('.tc-form-group');
            if ($group.hasClass('tc-field-error')) {
                // Fehler nur bei blur erneut prüfen
            }
        });
    });

})(jQuery);
