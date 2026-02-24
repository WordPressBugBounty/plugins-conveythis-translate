jQuery(document).ready(function ($) {
    var bootstrapDropdown = $.fn.dropdown.noConflict();
    $.fn.bootstrapDropdown = bootstrapDropdown;

    function checkTools() {
        if (conveythisSettings.effect && conveythisSettings.view) {
            conveythisSettings.effect(function () {
                $('#customize-view-button').transition('pulse');
            });
            conveythisSettings.view()

            $('.ui.dropdown').dropdown({
                onChange: function () {
                    conveythisSettings.view();
                }
            });
        } else {
            setTimeout(checkTools, 100);
        }
    }

    checkTools();

    $('#conveythis_api_key').on('input', async function () {
        var input = $(this);
        var inputValue = input.val();
        var validationLabel = $('#apiKey .validation-label');
        var validPattern = /^(pub_)?[a-zA-Z0-9]{32}$/;
        var buttons = $('.btn.btn-primary.btn-custom.autoSave, .nav-item button');

        if (!validPattern.test(inputValue)) {
            validationLabel.show().text("The api key you entered is invalid.");
            input.addClass('validation-failed');
            buttons.prop("disabled", true);
        } else {
            validationLabel.hide();
            input.removeClass('validation-failed');
            buttons.prop("disabled", false);
        }
    });

    $('#conveythis_api_key').on('change', async function () {
        var input = $(this);
        var inputValue = input.val();
        var validationLabel = $('#apiKey .validation-label');
        var buttons = $('.btn.btn-primary.btn-custom.autoSave, .nav-item button');

        $.ajax({
            url: 'https://api.conveythis.com/25/examination/pubkey/',
            method: 'POST',
            data: {'pub_key': inputValue},
            success: function (response) {
                if (response.data.check !== false) {
                    validationLabel.hide();
                    input.removeClass('validation-failed');
                    buttons.prop("disabled", false);
                } else {
                    input.addClass('validation-failed');
                    validationLabel.show().text("The api key you entered does not exist.");
                    buttons.prop("disabled", true);
                }
            },
            error: function () {
                alert('Server error, please contact support');
            }
        });
    });

    $('.conveythis_new_user').on('click', function () {
        jQuery.ajax({
            url: 'options.php',
            method: 'POST',
            data: {'ready_user': 1},
            success: function () {
                window.location.reload()
            },
            error: function () { },
        })

    })

    $('.api-key-setting').on('click', function () {
        console.log("$('.api-key-setting').on('click')")
        $('#login-form').css('display', 'none');
        $('#login-form-settings').css('display', 'block');

    })

    $('#refresh').on('click', function () {
        window.location.reload();
    });


    $('#conveythis-settings-form').on('submit', function (e) {
        e.preventDefault();
        return false;
    });

    $('#ajax-save-settings').on('click', function (e) {
        e.preventDefault();
        const $btn = $(this);
        const form = $('#conveythis-settings-form');
        if (!form.length) {
            console.error('[ConveyThis Save] Form #conveythis-settings-form not found');
            return;
        }
        const overlay = $('<div class="conveythis-overlay"></div>');
        form.css('position', 'relative').append(overlay);
        $btn.prop('disabled', true).val('Saving...');
        prepareSettingsBeforeSave();

        // Build glossary from DOM directly - never use form input (avoids truncation with many rules)
        var glossaryRules = getGlossaryRulesForSave();
        var glossaryJson = JSON.stringify(glossaryRules);
        $('#glossary_data').val(glossaryJson);

        // Properly handle array inputs from FormData
        const formData = new FormData(form[0]);
        const data = {};

        // Fields that should be preserved as JSON strings (not parsed as arrays)
        // CRITICAL: These fields must NEVER be converted to arrays
        const jsonStringFields = ['glossary', 'exclusions', 'exclusion_blocks', 'target_languages_translations', 'custom_css_json'];

        // Convert FormData to object, handling arrays properly
        for (let [key, value] of formData.entries()) {
            // SAFETY CHECK: Never process JSON string fields as arrays, even if they somehow have []
            if (jsonStringFields.includes(key)) {
                // This field is a JSON string - preserve it as-is, never convert to array
                data[key] = value;
                continue; // Skip array processing for this field
            }

            // Handle array inputs (fields ending with [])
            // IMPORTANT: Only process fields ending with [] as arrays
            // JSON string fields like 'glossary' should NOT end with []
            if (key.endsWith('[]')) {
                const arrayKey = key.slice(0, -2); // Remove '[]'

                // EXTRA SAFETY: Double-check this isn't a JSON string field
                if (jsonStringFields.includes(arrayKey)) {
                    console.error('[SAFETY ERROR] Attempted to process JSON field as array: ' + arrayKey);
                    // Preserve as string instead
                    data[arrayKey] = value;
                    continue;
                }

                // Only create array if key doesn't exist (prevents overwriting existing values)
                if (!data[arrayKey]) {
                    data[arrayKey] = [];
                }
                // Only add non-empty values
                if (value && value.trim() !== '') {
                    data[arrayKey].push(value);
                }
            } else {
                // Regular field - preserve as-is (including JSON strings)
                data[key] = value;
            }
        }

        // Force glossary from freshly collected rules (avoids FormData/input truncation on large payloads)
        data['glossary'] = glossaryJson;

        var ajaxUrl = typeof conveythis_plugin_ajax !== 'undefined' ? conveythis_plugin_ajax.ajax_url : '';
        if (!ajaxUrl) {
            console.error('[ConveyThis Save] conveythis_plugin_ajax.ajax_url is missing - cannot save');
            $('.conveythis-overlay').remove();
            $btn.prop('disabled', false).val('Save Settings');
            return;
        }

        // Send settings WITHOUT glossary so we don't duplicate the long string and risk hitting max_input_vars
        // (PHP drops excess vars; top-level 'glossary' could be lost and we'd fall back to truncated settings[glossary])
        var settingsToSend = Object.assign({}, data);
        delete settingsToSend.glossary;

        $.post(ajaxUrl, {
            action: 'conveythis_save_all_settings',
            nonce: data['conveythis_nonce'],
            settings: settingsToSend,
            glossary: glossaryJson
        }, function (response) {
            $('.conveythis-overlay').remove();
            $btn.prop('disabled', false).val('Save Settings');
            if (response.success) {
                toastr.success('Settings saved successfully');
                if (typeof syncGlossaryLanguageDropdowns === 'function') {
                    syncGlossaryLanguageDropdowns();
                    applyGlossaryFilters();
                }
            } else {
                console.error('[ConveyThis Glossary Save] Server returned success: false', response.data);
                toastr.error('Error saving settings: ' + (response.data && response.data.message ? response.data.message : response.data));
            }
        }).fail(function (xhr, status, err) {
            console.error('[ConveyThis Glossary Save] Request failed:', status, err);
            console.error('[ConveyThis Glossary Save] xhr.status:', xhr.status);
            console.error('[ConveyThis Glossary Save] xhr.responseText:', xhr.responseText ? xhr.responseText.substring(0, 500) : '');
            $('.conveythis-overlay').remove();
            $btn.prop('disabled', false).val('Save Settings');
        });
    });

    $('#register_form').submit((e) => {
        e.preventDefault()
        var values = {};
        jQuery.each($('#register_form').serializeArray(), function (i, field) {
            values[field.name] = field.value;
        });
        if (!values['i-agree']) {
            toastr.error('Agreeing to ConveyThis terms is required to register an account')
            return;
        }
        $('#signUpModal').modal('hide');
        jQuery.ajax({
            method: 'POST',
            url: 'https://app.conveythis.com/api/signup',
            data: {
                email: values['email'],
                domain: window.location.hostname,
                timestamp: Math.floor(Date.now() / 1000)
            },
            beforeSend: () => {
                $('.loader').show()
            },
            success: (res) => {
                if (res.status == 'success') {
                    iconType = 'success';
                    textTitle = 'Check your email and click on confirmation link';
                    textDescription = '<div class="loader-info"><p>You have successfully registered, please do not forget to confirm your email.<p/></div>';

                    newKey = res.data.pub_key;
                    var eventName = 'eventRegisterFormSuccess';
                    if (newKey) {
                        eventName = eventName + 'Key';
                    }

                    if (res.data.is_registered == true) {
                        iconType = 'warning';
                        textTitle = 'Your account already exists.';
                        textDescription = '<div class="loader-info"><p>Please go to <a target="_blank" href="https://app.conveythis.com/">ConveyThis</a> and log in. After that, you can copy API key for the plugin and apply it in the settings.<p/></div>';
                        eventName = 'accountAlreadyExists';
                    }

                    if (res.data.duplication_domain == true) {
                        iconType = 'error';
                        textTitle = 'Your domain has already been added.';
                        textDescription = '<div class="loader-info">' +
                            '<p>Please go to <a target="_blank" href="https://app.conveythis.com/">ConveyThis</a> and log in. After that, you can copy API key for the plugin and apply it in the settings.<p/>' +
                            '<p>If you have not registered this domain, please contact our <a href="mailto:support@conveythis.com">support</a>, we will definitely help you</p>' +
                            '</div>';
                        eventName = 'domainAlreadyExists';
                    }


                    if (newKey) {
                        toastr.success('Confirmation email was sent to your email')
                        $('#conveythis_api_key').val(newKey)
                        $('.loader').hide();
                        $('.next').click();
                    }

                    Swal.fire({
                        icon: iconType,
                        title: textTitle,
                        showCloseButton: true,
                        showConfirmButton: false,
                        html: textDescription,
                    }).then(() => {
                        if (iconType == 'success') {
                            const pusher = new Pusher(res.data.pusher_app_key, {
                                cluster: res.data.pusher_cluster,
                                encrypted: true
                            });

                            const channel = pusher.subscribe('notification_channel');
                            channel.bind('new_notification-' + res.data.tmp_pusher_id, function (data) {
                                $('#conveythis_api_key').val(data.api_key)
                                $('.loader').hide()
                                $('#signUpModal').modal('hide')
                                $('.next').click();
                            });

                            if (res.data.is_registered == false && !res.data.duplication_domain) {

                                setTimeout(() => {

                                    jQuery.ajax({
                                        url: 'options.php',
                                        method: 'POST',
                                        data: {'set_api_key': 1, 'api_key': res.data.pub_key, 'csrf': values.csrf},
                                        success: function () {
                                            window.location.reload()
                                        },
                                        error: function () { },
                                    })

                                })
                            }
                        }
                    })
                } else {
                    toastr.error(res.message)
                }
            },
            complete: () => {
                $('.loader').hide()
            }
        })
    })

    $('.signup-link a').click(() => {
        $('.login-link').show();
        $('.signup-link').hide()
        $('#register-div').show();
        $('#login-div').hide()
    })

    $('.login-link a').click(() => {
        $('.login-link').hide();
        $('.signup-link').show()
        $('#register-div').hide();
        $('#login-div').show()
    })
    var inputPassword = $('.input-psswd');
    $('.label-psswd').on('click', function () {
        if (inputPassword.attr('psswd-shown') == 'false') {
            $('.label-psswd .hide').show()
            $('.label-psswd .show').hide()
            inputPassword.removeAttr('type');
            inputPassword.attr('type', 'text');
            inputPassword.removeAttr('psswd-shown');
            inputPassword.attr('psswd-shown', 'true');
        } else {
            $('.label-psswd .hide').hide()
            $('.label-psswd .show').show()
            inputPassword.removeAttr('type');
            inputPassword.attr('type', 'password');
            inputPassword.removeAttr('psswd-shown');
            inputPassword.attr('psswd-shown', 'false');
        }
        $(this).toggleClass("active");
    });

    $('.signup-modal').click(() => {
        $('#signUpModal').modal('show')
    })

    var languages = {
        703: {
            'title_en': 'English',
            'title': 'English',
            'code2': 'en',
            'code3': 'eng',
            'flag': 'R04',
            'flag_codes': {'us': 'United States of America', 'gb': ' United Kingdom', 'ca': 'Canada', 'au': 'Australia'}
        },
        704: {
            'title_en': 'Afrikaans',
            'title': 'Afrikaans',
            'code2': 'af',
            'code3': 'afr',
            'flag': '7xS',
            'flag_codes': {
                'za': 'South Africa',
                'dz': 'Algeria',
                'td': 'Chad',
                'km': 'Comoros',
                'dj': 'Djibouti',
                'eg': 'Egypt',
                'er': 'Eritrea',
                'ly': 'Libya',
                'mr': 'Mauritania',
                'ma': 'Morocco',
                'so': 'Somalia',
                'sd': 'Sudan',
                'tn': 'Tunisia'
            }
        },
        705: {'title_en': 'Albanian', 'title': 'Shqip', 'code2': 'sq', 'code3': 'sqi', 'flag': '5iM'},
        706: {'title_en': 'Amharic', 'title': 'አማርኛ', 'code2': 'am', 'code3': 'amh', 'flag': 'ZH1'},
        707: {
            'title_en': 'Arabic',
            'title': 'العربية',
            'code2': 'ar',
            'code3': 'ara',
            'flag': 'J06',
            'flag_codes': {
                // Middle East
                'bh': 'Bahrain',
                'iq': 'Iraq',
                'jo': 'Jordan',
                'kw': 'Kuwait',
                'lb': 'Lebanon',
                'om': 'Oman',
                'ps': 'Palestine',
                'qa': 'Qatar',
                'sa': 'Saudi Arabia',
                'sy': 'Syria',
                'ae': 'United Arab Emirates',
                'ye': 'Yemen',
                // North Africa
                'dz': 'Algeria',
                'td': 'Chad',
                'km': 'Comoros',
                'dj': 'Djibouti',
                'eg': 'Egypt',
                'er': 'Eritrea',
                'ly': 'Libya',
                'mr': 'Mauritania',
                'ma': 'Morocco',
                'so': 'Somalia',
                'sd': 'Sudan',
                'tn': 'Tunisia'
            }
        },
        708: {'title_en': 'Armenian', 'title': 'Հայերեն', 'code2': 'hy', 'code3': 'hye', 'flag': 'q9U'},
        709: {'title_en': 'Azerbaijan', 'title': 'Azərbaycanca', 'code2': 'az', 'code3': 'aze', 'flag': 'Wg1'},
        710: {'title_en': 'Bashkir', 'title': 'Башҡортса', 'code2': 'ba', 'code3': 'bak', 'flag': 'D1H'},
        711: {'title_en': 'Basque', 'title': 'Euskara', 'code2': 'eu', 'code3': 'eus', 'flag': ''},
        712: {'title_en': 'Belarusian', 'title': 'Беларуская', 'code2': 'be', 'code3': 'bel', 'flag': 'O8S'},
        713: {'title_en': 'Bengali', 'title': 'বাংলা', 'code2': 'bn', 'code3': 'ben', 'flag': '63A'},
        714: {'title_en': 'Bosnian', 'title': 'Bosanski', 'code2': 'bs', 'code3': 'bos', 'flag': 'Z1t'},
        715: {'title_en': 'Bulgarian', 'title': 'Български', 'code2': 'bg', 'code3': 'bul', 'flag': 'V3p'},
        716: {'title_en': 'Burmese', 'title': 'မြန်မာဘာသာ', 'code2': 'my', 'code3': 'mya', 'flag': 'YB9'},
        717: {'title_en': 'Catalan', 'title': 'Català', 'code2': 'ca', 'code3': 'cat', 'flag': 'Pw6'},
        718: {'title_en': 'Cebuano', 'title': 'Cebuano', 'code2': 'ceb', 'code3': 'ceb', 'flag': ''},
        719: {
            'title_en': 'Chinese (Simplified)',
            'title': '简体',
            'code2': 'zh',
            'code3': 'zh-sim',
            'flag': 'Z1v',
            'flag_codes': {
                'cn': 'Mainland China',
                'sg': 'Singapore',
                'my': 'Malaysia'
            }
        },
        796: {
            'title_en': 'Chinese (Traditional)',
            'title': '繁體',
            'code2': 'zh-tw',
            'code3': 'zh-tra',
            'flag': 'Z1v',
            'flag_codes': {
                'tw': 'Taiwan',
                'hk': 'Hong Kong',
                'mo': 'Macau'
            }
        },
        720: {'title_en': 'Croatian', 'title': 'Hrvatski', 'code2': 'hr', 'code3': 'hrv', 'flag': '7KQ'},
        721: {'title_en': 'Czech', 'title': 'Čeština', 'code2': 'cs', 'code3': 'cze', 'flag': '1ZY'},
        722: {'title_en': 'Danish', 'title': 'Dansk', 'code2': 'da', 'code3': 'dan', 'flag': 'Ro2'},
        723: {'title_en': 'Dutch', 'title': 'Nederlands', 'code2': 'nl', 'code3': 'nld', 'flag': '8jV'},
        724: {'title_en': 'Esperanto', 'title': 'Esperanto', 'code2': 'eo', 'code3': 'epo', 'flag': 'Dw0'},
        725: {'title_en': 'Estonian', 'title': 'Eesti', 'code2': 'et', 'code3': 'est', 'flag': 'VJ8'},
        726: {
            'title_en': 'Finnish',
            'title': 'Suomi',
            'title': 'Finnish',
            'code2': 'fi',
            'code3': 'fin',
            'flag': 'nM4'
        },
        727: {
            'title_en': 'French',
            'title': 'Français',
            'code2': 'fr',
            'code3': 'fre',
            'flag': 'E77',
            'flag_codes': {'fr': 'France', 'ca': 'Canada'}
        },
        728: {'title_en': 'Galician', 'title': 'Galego', 'code2': 'gl', 'code3': 'glg', 'flag': 'A5d'},
        729: {'title_en': 'Georgian', 'title': 'ქართული', 'code2': 'ka', 'code3': 'kat', 'flag': '8Ou'},
        730: {'title_en': 'German', 'title': 'Deutsch', 'code2': 'de', 'code3': 'ger', 'flag': 'K7e'},
        731: {'title_en': 'Greek', 'title': 'Ελληνικά', 'code2': 'el', 'code3': 'ell', 'flag': 'kY8'},
        732: {'title_en': 'Gujarati', 'title': 'ગુજરાતી', 'code2': 'gu', 'code3': 'guj', 'flag': 'My6'},
        733: {'title_en': 'Haitian', 'title': 'Kreyòl Ayisyen', 'code2': 'ht', 'code3': 'hat', 'flag': ''},
        734: {'title_en': 'Hebrew', 'title': 'עברית', 'code2': 'he', 'code3': 'heb', 'flag': '5KS'},
        735: {'title_en': 'Hill Mari', 'title': 'Курыкмарий', 'code2': 'mrj', 'code3': 'mrj', 'flag': ''},
        736: {'title_en': 'Hindi', 'title': 'हिन्दी', 'code2': 'hi', 'code3': 'hin', 'flag': 'My6'},
        737: {'title_en': 'Hungarian', 'title': 'Magyar', 'code2': 'hu', 'code3': 'hun', 'flag': 'OU2'},
        738: {'title_en': 'Icelandic', 'title': 'Íslenska', 'code2': 'is', 'code3': 'isl', 'flag': 'Ho8'},
        739: {'title_en': 'Indonesian', 'title': 'Bahasa Indonesia', 'code2': 'id', 'code3': 'ind', 'flag': 't0X'},
        740: {'title_en': 'Irish', 'title': 'Gaeilge', 'code2': 'ga', 'code3': 'gle', 'flag': '5Tr'},
        741: {'title_en': 'Italian', 'title': 'Italiano', 'code2': 'it', 'code3': 'ita', 'flag': 'BW7'},
        742: {'title_en': 'Japanese', 'title': '日本語', 'code2': 'ja', 'code3': 'jpn', 'flag': '4YX'},
        743: {'title_en': 'Javanese', 'title': 'Basa Jawa', 'code2': 'jv', 'code3': 'jav', 'flag': 'C9k'},
        744: {'title_en': 'Kannada', 'title': 'ಕನ್ನಡ', 'code2': 'kn', 'code3': 'kan', 'flag': 'My6'},
        745: {'title_en': 'Kazakh', 'title': 'Қазақша', 'code2': 'kk', 'code3': 'kaz', 'flag': 'QA5'},
        746: {'title_en': 'Khmer', 'title': 'ភាសាខ្មែរ', 'code2': 'km', 'code3': 'khm', 'flag': 'o8B'},
        747: {'title_en': 'Korean', 'title': '한국어', 'code2': 'ko', 'code3': 'kor', 'flag': '0W3'},
        748: {'title_en': 'Kyrgyz', 'title': 'Кыргызча', 'code2': 'ky', 'code3': 'kir', 'flag': 'uP6'},
        749: {'title_en': 'Laotian', 'title': 'ພາສາລາວ', 'code2': 'lo', 'code3': 'lao', 'flag': 'Qy5'},
        750: {'title_en': 'Latin', 'title': 'Latina', 'code2': 'la', 'code3': 'lat', 'flag': 'BW7'},
        751: {'title_en': 'Latvian', 'title': 'Latviešu', 'code2': 'lv', 'code3': 'lav', 'flag': 'j1D'},
        752: {'title_en': 'Lithuanian', 'title': 'Lietuvių', 'code2': 'lt', 'code3': 'lit', 'flag': 'uI6'},
        753: {'title_en': 'Luxemb', 'title': 'Lëtzebuergesch', 'code2': 'lb', 'code3': 'ltz', 'flag': 'EV8'},
        754: {'title_en': 'Macedonian', 'title': 'Македонски', 'code2': 'mk', 'code3': 'mkd', 'flag': '6GV'},
        755: {'title_en': 'Malagasy', 'title': 'Malagasy', 'code2': 'mg', 'code3': 'mlg', 'flag': '4tE'},
        756: {'title_en': 'Malay', 'title': 'Bahasa Melayu', 'code2': 'ms', 'code3': 'msa', 'flag': 'C9k'},
        757: {'title_en': 'Malayalam', 'title': 'മലയാളം', 'code2': 'ml', 'code3': 'mal', 'flag': 'My6'},
        758: {'title_en': 'Maltese', 'title': 'Malti', 'code2': 'mt', 'code3': 'mlt', 'flag': 'N11'},
        759: {'title_en': 'Maori', 'title': 'Māori', 'code2': 'mi', 'code3': 'mri', 'flag': '0Mi'},
        760: {'title_en': 'Marathi', 'title': 'मराठी', 'code2': 'mr', 'code3': 'mar', 'flag': 'My6'},
        761: {'title_en': 'Mari', 'title': 'Мари́йский', 'code2': 'mhr', 'code3': 'chm', 'flag': ''},
        762: {'title_en': 'Mongolian', 'title': 'Монгол', 'code2': 'mn', 'code3': 'mon', 'flag': 'X8h'},
        763: {'title_en': 'Nepali', 'title': 'नेपाली', 'code2': 'ne', 'code3': 'nep', 'flag': 'E0c'},
        764: {'title_en': 'Norwegian', 'title': 'Norsk', 'code2': 'no', 'code3': 'nor', 'flag': '4KE'},
        765: {'title_en': 'Papiamento', 'title': 'E Papiamento', 'code2': 'pap', 'code3': 'pap', 'flag': ''},
        766: {'title_en': 'Persian', 'title': 'فارسی', 'code2': 'fa', 'code3': 'per', 'flag': 'Vo7'},
        767: {'title_en': 'Polish', 'title': 'Polski', 'code2': 'pl', 'code3': 'pol', 'flag': 'j0R'},
        768: {
            'title_en': 'Portuguese',
            'title': 'Português',
            'code2': 'pt',
            'code3': 'por',
            'flag': '1oU',
            'flag_codes': {'br': 'Brazil', 'pt': 'Portugal'}
        },
        769: {'title_en': 'Punjabi', 'title': 'ਪੰਜਾਬੀ', 'code2': 'pa', 'code3': 'pan', 'flag': 'n4T'},
        770: {'title_en': 'Romanian', 'title': 'Română', 'code2': 'ro', 'code3': 'rum', 'flag': 'V5u'},
        771: {'title_en': 'Russian', 'title': 'Русский', 'code2': 'ru', 'code3': 'rus', 'flag': 'D1H'},
        772: {'title_en': 'Scottish', 'title': 'Gàidhlig', 'code2': 'gd', 'code3': 'gla', 'flag': '9MI'},
        773: {'title_en': 'Serbian', 'title': 'Српски', 'code2': 'sr', 'code3': 'srp', 'flag': 'GC6'},
        774: {'title_en': 'Sinhala', 'title': 'සිංහල', 'code2': 'si', 'code3': 'sin', 'flag': '9JL'},
        775: {'title_en': 'Slovakian', 'title': 'Slovenčina', 'code2': 'sk', 'code3': 'slk', 'flag': 'Y2i'},
        776: {'title_en': 'Slovenian', 'title': 'Slovenščina', 'code2': 'sl', 'code3': 'slv', 'flag': 'ZR1'},
        777: {
            'title_en': 'Spanish',
            'title': 'Español',
            'code2': 'es',
            'code3': 'spa',
            'flag': 'A5d',
            'flag_codes': {'mx': 'Mexico', 'ar': 'Argentina', 'co': 'Colombia', 'es': 'Spain'}
        },
        778: {'title_en': 'Sundanese', 'title': 'Basa Sunda', 'code2': 'su', 'code3': 'sun', 'flag': 'Wh1'},
        779: {'title_en': 'Swahili', 'title': 'Kiswahili', 'code2': 'sw', 'code3': 'swa', 'flag': 'X3y'},
        780: {'title_en': 'Swedish', 'title': 'Svenska', 'code2': 'sv', 'code3': 'swe', 'flag': 'oZ3'},
        781: {'title_en': 'Tagalog', 'title': 'Tagalog', 'code2': 'tl', 'code3': 'tgl', 'flag': '2qL'},
        782: {'title_en': 'Tajik', 'title': 'Тоҷикӣ', 'code2': 'tg', 'code3': 'tgk', 'flag': '7Qa'},
        783: {'title_en': 'Tamil', 'title': 'தமிழ்', 'code2': 'ta', 'code3': 'tam', 'flag': 'My6'},
        784: {'title_en': 'Tatar', 'title': 'Татарча', 'code2': 'tt', 'code3': 'tat', 'flag': 'D1H'},
        785: {'title_en': 'Telugu', 'title': 'తెలుగు', 'code2': 'te', 'code3': 'tel', 'flag': 'My6'},
        786: {'title_en': 'Thai', 'title': 'ภาษาไทย', 'code2': 'th', 'code3': 'tha', 'flag': 'V6r'},
        787: {'title_en': 'Turkish', 'title': 'Türkçe', 'code2': 'tr', 'code3': 'tur', 'flag': 'YZ9'},
        788: {'title_en': 'Udmurt', 'title': 'Удму́рт дунне́', 'code2': 'udm', 'code3': 'udm', 'flag': ''},
        789: {'title_en': 'Ukrainian', 'title': 'Українська', 'code2': 'uk', 'code3': 'ukr', 'flag': '2Mg'},
        790: {'title_en': 'Urdu', 'title': 'اردو', 'code2': 'ur', 'code3': 'urd', 'flag': 'n4T'},
        791: {'title_en': 'Uzbek', 'title': 'O‘zbek', 'code2': 'uz', 'code3': 'uzb', 'flag': 'zJ3'},
        792: {'title_en': 'Vietnamese', 'title': 'Tiếng Việt', 'code2': 'vi', 'code3': 'vie', 'flag': 'l2A'},
        793: {'title_en': 'Welsh', 'title': 'Cymraeg', 'code2': 'cy', 'code3': 'wel', 'flag': 'D4b'},
        794: {'title_en': 'Xhosa', 'title': 'isiXhosa', 'code2': 'xh', 'code3': 'xho', 'flag': '7xS'},
        795: {'title_en': 'Yiddish', 'title': 'ייִדיש', 'code2': 'yi', 'code3': 'yid', 'flag': '5KS'},
        //796:{'title_en':'Chinese (Traditional)','title':'繁體','code2':'zh-TW','code3':'zh-tra','flag':'Z1v'},
        797: {'title_en': 'Somali', 'title': 'Soomaali', 'code2': 'so', 'code3': 'som', 'flag': '3fH'},
        798: {'title_en': 'Corsican', 'title': 'Corsu', 'code2': 'co', 'code3': 'cos', 'flag': 'E77'},
        799: {'title_en': 'Frisian', 'title': 'Frysk', 'code2': 'fy', 'code3': 'fry', 'flag': '8jV'},
        800: {'title_en': 'Hausa', 'title': 'Hausa', 'code2': 'ha', 'code3': 'hau', 'flag': '8oM'},
        801: {'title_en': 'Hawaiian', 'title': 'Ōlelo Hawaiʻi', 'code2': 'haw', 'code3': 'haw', 'flag': '00P'},
        802: {'title_en': 'Hmong', 'title': 'Hmong', 'code2': 'hmn', 'code3': 'hmn', 'flag': 'Z1v'},
        803: {'title_en': 'Igbo', 'title': 'Igbo', 'code2': 'ig', 'code3': 'ibo', 'flag': '8oM'},
        804: {'title_en': 'Kinyarwanda', 'title': 'Kinyarwanda', 'code2': 'rw', 'code3': 'kin', 'flag': '8UD'},
        805: {'title_en': 'Kurdish', 'title': 'Kurdî', 'code2': 'ku', 'code3': 'kur', 'flag': 'YZ9'},
        806: {'title_en': 'Chichewa', 'title': 'Chichewa', 'code2': 'ny', 'code3': 'nya', 'flag': 'O9C'},
        807: {'title_en': 'Odia', 'title': 'ଓଡିଆ', 'code2': 'or', 'code3': 'ori', 'flag': 'My6'},
        808: {'title_en': 'Samoan', 'title': 'Faasamoa', 'code2': 'sm', 'code3': 'smo', 'flag': '54E'},
        809: {'title_en': 'Sesotho', 'title': 'Sesotho', 'code2': 'st', 'code3': 'sot', 'flag': '7xS'},
        810: {'title_en': 'Shona', 'title': 'Shona', 'code2': 'sn', 'code3': 'sna', 'flag': '80Y'},
        811: {'title_en': 'Sindhi', 'title': 'سنڌي', 'code2': 'sd', 'code3': 'snd', 'flag': 'n4T'},
        812: {'title_en': 'Turkmen', 'title': 'Türkmenler', 'code2': 'tk', 'code3': 'tuk', 'flag': 'Tm5'},
        813: {'title_en': 'Uyghur', 'title': 'ئۇيغۇر', 'code2': 'ug', 'code3': 'uig', 'flag': 'Z1v'},
        814: {'title_en': 'Yoruba', 'title': 'Yoruba', 'code2': 'yo', 'code3': 'yor', 'flag': '8oM'},
        815: {'title_en': 'Zulu', 'title': 'Zulu', 'code2': 'zu', 'code3': 'zul', 'flag': '7xS'},
        816: {
            'title_en': 'Portuguese (PT)',
            'title': 'Português (PT)',
            'code2': 'pt-pt',
            'code3': 'por',
            'flag': '1oU'
        },
        817: {
            'title_en': 'Portuguese (BR)',
            'title': 'Português (BR)',
            'code2': 'pt-br',
            'code3': 'por',
            'flag': '1oU'
        },
        // New Languages
        818: {'title_en': 'Abkhaz', 'title': 'Abkhaz', 'code2': 'ab', 'code3': 'abk', 'flag': 'ab'},
        819: {'title_en': 'Acehnese', 'title': 'Acehnese', 'code2': 'ace', 'code3': 'aceh', 'flag': 't0X'},
        820: {'title_en': 'Acholi', 'title': 'Acholi', 'code2': 'ach', 'code3': 'acho', 'flag': 'ach'},
        821: {'title_en': 'Alur', 'title': 'Alur', 'code2': 'alz', 'code3': 'alu', 'flag': 'eJ2'},
        822: {'title_en': 'Assamese', 'title': 'Assamese', 'code2': 'as', 'code3': 'asm', 'flag': 'My6'},
        823: {'title_en': 'Awadhi', 'title': 'Awadhi', 'code2': 'awa', 'code3': 'awa', 'flag': 'My6'},
        824: {'title_en': 'Aymara', 'title': 'Aymara', 'code2': 'ay', 'code3': 'aym', 'flag': 'aym'},
        825: {'title_en': 'Balinese', 'title': 'Balinese', 'code2': 'ban', 'code3': 'ban', 'flag': 'My6'},
        826: {'title_en': 'Bambara', 'title': 'Bambara', 'code2': 'bm', 'code3': 'bam', 'flag': 'Yi5'},
        827: {'title_en': 'Batak Karo', 'title': 'Batak Karo', 'code2': 'btx', 'code3': 'btx', 'flag': 'My6'},
        828: {'title_en': 'Batak Simalungun', 'title': 'Batak Simalungun', 'code2': 'bts', 'code3': 'bts', 'flag': 'My6'},
        829: {'title_en': 'Batak Toba', 'title': 'Batak Toba', 'code2': 'bbc', 'code3': 'bbc', 'flag': 'My6'},
        830: {'title_en': 'Bemba', 'title': 'Bemba', 'code2': 'bem', 'code3': 'bem', 'flag': '9Be'},
        831: {'title_en': 'Betawi', 'title': 'Betawi', 'code2': 'bew', 'code3': 'bew', 'flag': 't0X'},
        832: {'title_en': 'Bhojpuri', 'title': 'Bhojpuri', 'code2': 'bho', 'code3': 'bho', 'flag': 'My6'},
        833: {'title_en': 'Bikol', 'title': 'Bikol', 'code2': 'bik', 'code3': 'bik', 'flag': '2qL'},
        834: {'title_en': 'Bodo', 'title': 'Bodo', 'code2': 'brx', 'code3': 'brx', 'flag': 'My6'},
        835: {'title_en': 'Breton', 'title': 'Breton', 'code2': 'br', 'code3': 'bre', 'flag': 'bre'},
        836: {'title_en': 'Buryat', 'title': 'Buryat', 'code2': 'bua', 'code3': 'bua', 'flag': 'bur'},
        837: {'title_en': 'Cantonese', 'title': 'Cantonese', 'code2': 'yue', 'code3': 'yue', 'flag': '00H'},
        838: {'title_en': 'Chhattisgarhi', 'title': 'Chhattisgarhi', 'code2': 'hne', 'code3': 'hne', 'flag': 'My6'},
        839: {'title_en': 'Chuvash', 'title': 'Chuvash', 'code2': 'cv', 'code3': 'chv', 'flag': 'chv'},
        840: {'title_en': 'Crimean Tatar', 'title': 'Crimean Tatar', 'code2': 'crh', 'code3': 'crh', 'flag': 'crh'},
        841: {'title_en': 'Dari', 'title': 'Dari', 'code2': 'fa-af', 'code3': 'prs', 'flag': 'NV2'},
        842: {'title_en': 'Dinka', 'title': 'Dinka', 'code2': 'din', 'code3': 'din', 'flag': 'H4u'},
        843: {'title_en': 'Divehi', 'title': 'Divehi', 'code2': 'dv', 'code3': 'div', 'flag': '1Q3'},
        844: {'title_en': 'Dogri', 'title': 'Dogri', 'code2': 'doi', 'code3': 'doi', 'flag': 'My6'},
        845: {'title_en': 'Dombe', 'title': 'Dombe', 'code2': 'dov', 'code3': 'dov', 'flag': '80Y'},
        846: {'title_en': 'Dzongkha', 'title': 'Dzongkha', 'code2': 'dz', 'code3': 'dzo', 'flag': 'D9z'},
        847: {'title_en': 'Ewe', 'title': 'Ewe', 'code2': 'ee', 'code3': 'ewe', 'flag': 'ewe'},
        848: {'title_en': 'Faroese', 'title': 'Faroese', 'code2': 'fo', 'code3': 'fao', 'flag': 'fo'},
        849: {'title_en': 'Fijian', 'title': 'Fijian', 'code2': 'fj', 'code3': 'fij', 'flag': 'E1f'},
        850: {'title_en': 'Fulfulde', 'title': 'Fulfulde', 'code2': 'ff', 'code3': 'ful', 'flag': '8oM'},
        851: {'title_en': 'Ga', 'title': 'Ga', 'code2': 'gaa', 'code3': 'gaa', 'flag': '6Mr'},
        852: {'title_en': 'Ganda', 'title': 'Ganda', 'code2': 'lg', 'code3': 'lug', 'flag': 'eJ2'},
        853: {'title_en': 'Guarani', 'title': 'Guarani', 'code2': 'gn', 'code3': 'grn', 'flag': 'y5O'},
        854: {'title_en': 'Hakha Chin', 'title': 'Hakha Chin', 'code2': 'cnh', 'code3': 'cnh', 'flag': 'YB9'},
        855: {'title_en': 'Hiligaynon', 'title': 'Hiligaynon', 'code2': 'hil', 'code3': 'hil', 'flag': '2qL'},
        856: {'title_en': 'Hunsrik', 'title': 'Hunsrik', 'code2': 'hrx', 'code3': 'hrx', 'flag': '1oU'},
        857: {'title_en': 'Iloko', 'title': 'Iloko', 'code2': 'ilo', 'code3': 'ilo', 'flag': '2qL'},
        858: {'title_en': 'Inuinnaqtun', 'title': 'Inuinnaqtun', 'code2': 'ikt', 'code3': 'ikt', 'flag': 'P4g'},
        859: {'title_en': 'Inuktitut', 'title': 'Inuktitut', 'code2': 'iu', 'code3': 'iku', 'flag': 'P4g'},
        860: {'title_en': 'Kapampangan', 'title': 'Kapampangan', 'code2': 'pam', 'code3': 'pam', 'flag': '2qL'},
        861: {'title_en': 'Kashmiri', 'title': 'Kashmiri', 'code2': 'ks', 'code3': 'kas', 'flag': 'My6'},
        862: {'title_en': 'Kiga', 'title': 'Kiga', 'code2': 'cgg', 'code3': 'cgg', 'flag': 'eJ2'},
        863: {'title_en': 'Kituba', 'title': 'Kituba', 'code2': 'ktu', 'code3': 'ktu', 'flag': 'WK0'},
        865: {'title_en': 'Konkani', 'title': 'Konkani', 'code2': 'gom', 'code3': 'gom', 'flag': 'My6'},
        866: {'title_en': 'Krio', 'title': 'Krio', 'code2': 'kri', 'code3': 'kri', 'flag': 'mS4'},
        867: {'title_en': 'Kurdish (Central)', 'title': 'Kurdish (Central)', 'code2': 'ckb', 'code3': 'ckb', 'flag': 'ckb'},
        868: {'title_en': 'Latgalian', 'title': 'Latgalian', 'code2': 'ltg', 'code3': 'ltg', 'flag': 'j1D'},
        869: {'title_en': 'Ligurian', 'title': 'Ligurian', 'code2': 'lij', 'code3': 'lij', 'flag': 'BW7'},
        870: {'title_en': 'Limburgan', 'title': 'Limburgan', 'code2': 'li', 'code3': 'lim', 'flag': '8jV'},
        871: {'title_en': 'Lingala', 'title': 'Lingala', 'code2': 'ln', 'code3': 'lin', 'flag': 'Kv5'},
        872: {'title_en': 'Lombard', 'title': 'Lombard', 'code2': 'lmo', 'code3': 'lmo', 'flag': 'BW7'},
        873: {'title_en': 'Lower Sorbian', 'title': 'Lower Sorbian', 'code2': 'dsb', 'code3': 'dsb', 'flag': 'K7e'},
        874: {'title_en': 'Luo', 'title': 'Luo', 'code2': 'luo', 'code3': 'luo', 'flag': 'X3y'},
        875: {'title_en': 'Maithili', 'title': 'Maithili', 'code2': 'mai', 'code3': 'mai', 'flag': 'E0c'},
        876: {'title_en': 'Makassar', 'title': 'Makassar', 'code2': 'mak', 'code3': 'mak', 'flag': 't0X'},
        877: {'title_en': 'Manipuri', 'title': 'Manipuri', 'code2': 'mni-mtei', 'code3': 'mni', 'flag': 'My6'},
        878: {'title_en': 'Meadow Mari', 'title': 'Meadow Mari', 'code2': 'chm', 'code3': 'chm', 'flag': 'D1H'},
        879: {'title_en': 'Minang', 'title': 'Minang', 'code2': 'min', 'code3': 'min', 'flag': 't0X'},
        880: {'title_en': 'Mizo', 'title': 'Mizo', 'code2': 'lus', 'code3': 'lus', 'flag': 'My6'},
        881: {'title_en': 'Ndebele (South)', 'title': 'Ndebele (South)', 'code2': 'nr', 'code3': 'nbl', 'flag': '80Y'},
        882: {'title_en': 'Nepalbhasa', 'title': 'Nepalbhasa', 'code2': 'new', 'code3': 'new', 'flag': 'E0c'},
        883: {'title_en': 'Northern Sotho', 'title': 'Northern Sotho', 'code2': 'nso', 'code3': 'nso', 'flag': '7xS'},
        884: {'title_en': 'Nuer', 'title': 'Nuer', 'code2': 'nus', 'code3': 'nus', 'flag': 'H4u'},
        885: {'title_en': 'Occitan', 'title': 'Occitan', 'code2': 'oc', 'code3': 'oci', 'flag': 'E77'},
        886: {'title_en': 'Oromo', 'title': 'Oromo', 'code2': 'om', 'code3': 'orm', 'flag': 'ZH1'},
        887: {'title_en': 'Pangasinan', 'title': 'Pangasinan', 'code2': 'pag', 'code3': 'pag', 'flag': '2qL'},
        888: {'title_en': 'Pashto', 'title': 'Pashto', 'code2': 'ps', 'code3': 'pus', 'flag': 'NV2'},
        889: {'title_en': 'Quechua', 'title': 'Quechua', 'code2': 'qu', 'code3': 'que', 'flag': '4MJ'},
        890: {'title_en': 'Queretaro Otomi', 'title': 'Queretaro Otomi', 'code2': 'otq', 'code3': 'otq', 'flag': '8Qb'},
        891: {'title_en': 'Romani', 'title': 'Romani', 'code2': 'rom', 'code3': 'rom', 'flag': 'V5u'},
        892: {'title_en': 'Rundi', 'title': 'Rundi', 'code2': 'rn', 'code3': 'run', 'flag': '5qZ'},
        893: {'title_en': 'Sango', 'title': 'Sango', 'code2': 'sg', 'code3': 'sag', 'flag': 'kN9'},
        894: {'title_en': 'Sanskrit', 'title': 'Sanskrit', 'code2': 'sa', 'code3': 'san', 'flag': 'My6'},
        895: {'title_en': 'Seychellois Creole', 'title': 'Seychellois Creole', 'code2': 'crs', 'code3': 'crs', 'flag': 'JE6'},
        896: {'title_en': 'Shan', 'title': 'Shan', 'code2': 'shn', 'code3': 'shn', 'flag': 'YB9'},
        897: {'title_en': 'Sicilian', 'title': 'Sicilian', 'code2': 'scn', 'code3': 'scn', 'flag': 'BW7'},
        898: {'title_en': 'Silesian', 'title': 'Silesian', 'code2': 'szl', 'code3': 'szl', 'flag': 'j0R'},
        899: {'title_en': 'Swati', 'title': 'Swati', 'code2': 'ss', 'code3': 'ssw', 'flag': 'f6L'},
        900: {'title_en': 'Tahitian', 'title': 'Tahitian', 'code2': 'ty', 'code3': 'tah', 'flag': 'E77'},
        901: {'title_en': 'Tetum', 'title': 'Tetum', 'code2': 'tet', 'code3': 'tet', 'flag': '52C'},
        902: {'title_en': 'Tibetan', 'title': 'Tibetan', 'code2': 'bo', 'code3': 'bod', 'flag': 'Z1v'},
        903: {'title_en': 'Tigrinya', 'title': 'Tigrinya', 'code2': 'ti', 'code3': 'tir', 'flag': '8Gl'},
        904: {'title_en': 'Tongan', 'title': 'Tongan', 'code2': 'to', 'code3': 'ton', 'flag': '8Ox'},
        905: {'title_en': 'Tsonga', 'title': 'Tsonga', 'code2': 'ts', 'code3': 'tso', 'flag': '7xS'},
        906: {'title_en': 'Tswana', 'title': 'Tswana', 'code2': 'tn', 'code3': 'tsn', 'flag': 'Vf3'},
        907: {'title_en': 'Twi', 'title': 'Twi', 'code2': 'ak', 'code3': 'aka', 'flag': '6Mr'},
        908: {'title_en': 'Upper Sorbian', 'title': 'Upper Sorbian', 'code2': 'hsb', 'code3': 'hsb', 'flag': 'K7e'},
        909: {'title_en': 'Yucatec Maya', 'title': 'Yucatec Maya', 'code2': 'yua', 'code3': 'yua', 'flag': '8Qb'},

        // New Languages
        910: {'title_en': 'Arabic (Egypt)', 'title': 'Arabic (Egypt)', 'code2': 'ar-eg', 'code3': 'ara', 'flag': 'eg'},
        911: {'title_en': 'Arabic (UAE)', 'title': 'Arabic (UAE)', 'code2': 'ar-ae', 'code3': 'ara', 'flag': 'ae'},
        912: {'title_en': 'English (UK)', 'title': 'English (UK)', 'code2': 'en-gb', 'code3': 'eng', 'flag': 'gb'},
        913: {'title_en': 'English (Australia)', 'title': 'English (Australia)', 'code2': 'en-au', 'code3': 'eng', 'flag': 'au'},
        914: {'title_en': 'Spanish (Mexico)', 'title': 'Spanish (Mexico)', 'code2': 'es-mx', 'code3': 'spa', 'flag': 'mx'},
        915: {'title_en': 'Spanish (USA)', 'title': 'Spanish (USA)', 'code2': 'es-us', 'code3': 'spa', 'flag': 'us'},
        916: {'title_en': 'French (Canada)', 'title': 'French (Canada)', 'code2': 'fr-ca', 'code3': 'fre', 'flag': 'ca'},
    }

    $("#range-style-indenting-vertical").slider({
        min: 0,
        max: 300,
        start: $("#display-style-indenting-vertical").text(),
        onMove: function (value) {
            $("#display-style-indenting-vertical").html(value);
            $("[name=style_indenting_vertical]").val(value);
        }
    });
    $("#range-style-indenting-horizontal").slider({
        min: 0,
        max: 300,
        start: $("#display-style-indenting-horizontal").text(),
        onMove: function (value) {
            $("#display-style-indenting-horizontal").html(value);
            $("[name=style_indenting_horizontal]").val(value);
        }
    });

    // Prevents the user from removing the flag and text from the widget.
    $(".radio-block").on("click", function () {
        let withoutTextChecked = $("#without-text").is(":checked");
        let withoutFlagChecked = $("#without-flag").is(":checked");

        if (withoutTextChecked && withoutFlagChecked) {
            $(".notify").css("display", "block");
            $(".btn-primary").prop("disabled", true);
        } else {
            $(".notify").css("display", "none");
            $(".btn-primary").prop("disabled", false);
        }
    });

    //

    $('.ui.dropdown').dropdown(
        {
            onChange: function (e) {
                if ($(this).hasClass('widget-trigger')) {
                    hideTargetLanguage();
                    conveythisSettings.view();
                }
                showDnsRecords();
            }
        }
    );

    conveythisSettings.effect(function () {
        $('#customize-view-button').transition('pulse');
    });
    conveythisSettings.view();


    $('.conveythis-widget-option-form input[name=style_corner_type]').on('change', function () {
        conveythisSettings.view();
    });

    $('.conveythis-widget-option-form .form-control-color').on('change', function () {
        conveythisSettings.view();
    });

    $('button.btn-default-color').click(function () {
        let colorInput = $(this).parent().find("input[type=color]");
        let defaultColor = colorInput.data("default");

        colorInput.val(defaultColor);
        conveythisSettings.view();
    });

    $('.conveythis-reset').on('click', function (e) {
        e.preventDefault();
        $(this).parent().parent().find('.ui.dropdown').dropdown('clear');
    });

    $('.conveythis-delete-page').on('click', function (e) {
        if ($(this).closest('#glossary_wrapper').length) return;
        e.preventDefault();
        let $rowToDelete = $(this).closest('.style-language');
        if ($rowToDelete.length) {
            $rowToDelete.remove();
            updateLanguageDropdownAvailability();
        } else {
            $(this).parent().remove();
        }
    });

    $('#add_blockpage').on('click', function (e) {
        e.preventDefault();

        let blockpage = '<div class="blockpage position-relative w-100 pe-4">\n' +
            '                    <button class="conveythis-delete-page"></button>\n' +
            '                    <div class="ui input w-100"><input type="url" name="blockpages[]" class="ui input w-100" placeholder="https://example.com"></div>\n' +
            '                </div>';
        $("#blockpages_wrapper").append(blockpage);

        $(document).find('.conveythis-delete-page').on('click', function (e) {
            e.preventDefault();
            $(this).parent().remove();
        });

    });

    $('#add_exlusion').on('click', function (e) {
        e.preventDefault();
        let $exclusion = $('<div class="exclusion d-flex position-relative w-100 pe-4">\n' +
            '                    <button class="conveythis-delete-page"></button>\n' +
            '                    <div class="dropdown me-3">\n' +
            '                        <i class="dropdown icon"></i>\n' +
            '                        <select class="dropdown fluid ui form-control rule selection">\n' +
            '                            <option value="start">Start</option>\n' +
            '                            <option value="end">End</option>\n' +
            '                            <option value="contain">Contain</option>\n' +
            '                            <option value="equal">Equal</option>\n' +
            '                        </select>\n' +
            '                    </div>\n' +
            '                     <div class="ui input w-100"><input type="text" class="page_url w-100" placeholder="https://example.com" value=""></div>\n' +
            '                </div>');

        $("#exclusion_wrapper").append($exclusion);

        $(document).find('.conveythis-delete-page').on('click', function (e) {
            e.preventDefault();
            $(this).parent().remove();
        });

        $('.ui.dropdown').dropdown()
    });

    $('#add_flag_style').on('click', function (e) {
        e.preventDefault();

        if ($(".style-language").length == 6) { // 1 cloned template + 5 actual rows = 6 total
            $('#add_flag_style').prop("disable", true);
            return;
        }

        let $rule_style = $('.cloned').clone()

        $rule_style.removeClass('cloned')
        $rule_style.find('input[name="style_change_language[]"]').val('');
        $rule_style.find('input[name="style_change_flag[]"]').val('');
        $("#flag-style_wrapper").append($rule_style);

        $(document).find('.conveythis-delete-page').on('click', function (e) {
            e.preventDefault();
            let $rowToDelete = $(this).closest('.style-language');
            $rowToDelete.remove();

            // Update language availability after row deletion
            updateLanguageDropdownAvailability();
        });

        $('.ui.dropdown').dropdown();
        // Re-initialize handlers for all dropdowns (including the new one)
        sortFlagsByLanguage();
        // Initialize only the newly added row
        initializeFlagDropdowns();
    });

    // Initialize on page load
    sortFlagsByLanguage();
    initializeFlagDropdowns();

    var GLOSSARY_PER_PAGE = 20;
    var glossaryCurrentPage = 1;
    var glossaryTotalPages = 1;

    function applyGlossaryFilters() {
        var $panel = $('#v-pills-glossary');
        var searchQuery = ($panel.find('#glossary_search').val() || '').trim().toLowerCase();
        var filterLang = ($panel.find('#glossary_filter_language').val() || '') || '';
        var $rows = $('#glossary_wrapper').children('.glossary');
        var visibleIndices = [];
        $rows.each(function (idx) {
            var $row = $(this);
            var rowLang = $row.data('target-language');
            if (rowLang === undefined || rowLang === null) {
                var $langSelect = $row.find('select.target_language');
                rowLang = $langSelect.length ? ($langSelect.val() || '') : ($row.find('.row select').last().val() || '');
            } else {
                rowLang = String(rowLang);
            }
            var langMatch;
            if (filterLang === '') {
                langMatch = true;
            } else if (filterLang === '__all__') {
                langMatch = rowLang === '';
            } else {
                langMatch = rowLang === filterLang;
            }
            var searchMatch = true;
            if (searchQuery) {
                var sourceText = ($row.find('input.source_text').val() || '').toLowerCase();
                var translateText = ($row.find('input.translate_text').val() || '').toLowerCase();
                searchMatch = sourceText.indexOf(searchQuery) !== -1 || translateText.indexOf(searchQuery) !== -1;
            }
            var passesFilter = langMatch && searchMatch;
            if (passesFilter) visibleIndices.push(idx);
        });
        var totalVisible = visibleIndices.length;
        var totalPages = Math.max(1, Math.ceil(totalVisible / GLOSSARY_PER_PAGE));
        glossaryCurrentPage = Math.min(Math.max(1, glossaryCurrentPage), totalPages);
        var start = (glossaryCurrentPage - 1) * GLOSSARY_PER_PAGE;
        var end = start + GLOSSARY_PER_PAGE;
        var visibleSet = {};
        for (var i = start; i < end && i < visibleIndices.length; i++) {
            visibleSet[visibleIndices[i]] = true;
        }
        $rows.each(function (idx) {
            var passesFilter = visibleIndices.indexOf(idx) !== -1;
            var onCurrentPage = !!visibleSet[idx];
            var show = passesFilter && onCurrentPage;
            $(this).css('display', show ? '' : 'none');
        });
        glossaryTotalPages = totalPages;
        var $pagination = $('#glossary_pagination');
        if (totalVisible > GLOSSARY_PER_PAGE) {
            $pagination.show();
            $('#glossary_page_info').text('Page ' + glossaryCurrentPage + ' of ' + totalPages + ' (' + totalVisible + ' rules)');
            $('#glossary_prev_page').prop('disabled', glossaryCurrentPage <= 1);
            $('#glossary_next_page').prop('disabled', glossaryCurrentPage >= totalPages);
        } else {
            $pagination.hide();
        }
    }

    $(document).on('click', '#glossary_prev_page', function (e) {
        e.preventDefault();
        if (glossaryCurrentPage > 1) {
            glossaryCurrentPage--;
            applyGlossaryFilters();
        }
    });
    $(document).on('click', '#glossary_next_page', function (e) {
        e.preventDefault();
        if (glossaryCurrentPage < glossaryTotalPages) {
            glossaryCurrentPage++;
            applyGlossaryFilters();
        }
    });

    $(document).on('input', '#glossary_search', function () {
        glossaryCurrentPage = 1;
        applyGlossaryFilters();
    });

    $(document).on('change', '#glossary_filter_language', function () {
        glossaryCurrentPage = 1;
        applyGlossaryFilters();
    });

    $(document).on('change', '#glossary_wrapper select.target_language', function () {
        var val = $(this).val() || '';
        $(this).closest('.glossary').data('target-language', val);
    });

    $(document).on('click', '[data-action="delete-glossary-row"]', function (e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        var $row = $(this).closest('.glossary');
        if ($row.length) {
            $row.remove();
            applyGlossaryFilters();
        }
        return false;
    });

    function appendGlossaryRow(ruleData) {
        ruleData = ruleData || {};
        var sourceText = ruleData.source_text || '';
        var rule = ruleData.rule === 'replace' ? 'replace' : 'prevent';
        var translateText = ruleData.translate_text || '';
        var targetLang = ruleData.target_language || '';
        var targetLanguages = ($('input[name="target_languages"]').val() || '').trim().split(',').map(function (s) { return s.trim(); }).filter(Boolean);
        var $glossary = $('<div class="glossary position-relative w-100">\n' +
            '                        <a role="button" class="conveythis-delete-page glossary-delete-btn" data-action="delete-glossary-row" style="top:10px" aria-label="Delete rule"></a>\n' +
            '                        <div class="row w-100 mb-2">\n' +
            '                            <div class="col-md-3">\n' +
            '                                <div class="ui input">\n' +
            '                                    <input type="text" class="source_text w-100 conveythis-input-text" placeholder="Enter Word" value="">\n' +
            '                                </div>\n' +
            '                            </div>\n' +
            '                            <div class="col-md-3">\n' +
            '                                <select class="form-control rule w-100" required>\n' +
            '                                    <option value="prevent">Don\'t translate</option>\n' +
            '                                    <option value="replace">Translate as</option>\n' +
            '                                </select>\n' +
            '                            </div>\n' +
            '                            <div class="col-md-3">\n' +
            '                                <div class="ui input">\n' +
            '                                    <input type="text" class="conveythis-input-text translate_text w-100" disabled>\n' +
            '                                </div>\n' +
            '                            </div>\n' +
            '                            <div class="col-md-3">\n' +
            '                                <select class="form-control target_language w-100">\n' +
            '                                    <option value="">All languages</option>\n' +
            '                                </select>\n' +
            '                            </div>\n' +
            '                        </div>\n' +
            '                    </div>');


        var $targetLanguagesSelect = $glossary.find('select.target_language');
        for (var language_id in languages) {
            var language = languages[language_id];
            if (targetLanguages.indexOf(language.code2) !== -1) {
                $targetLanguagesSelect.append('<option value="' + language.code2 + '">' + language.title_en + '</option>');
            }
        }

        $glossary.find('input.source_text').val(sourceText);
        $glossary.find('select.rule').val(rule);
        $glossary.find('input.translate_text').val(translateText);
        var shouldEnable = (rule === 'replace');
        $glossary.find('input.translate_text').prop('disabled', !shouldEnable);
        $targetLanguagesSelect.val(targetLang);
        $glossary.data('target-language', targetLang);

        $("#glossary_wrapper").append($glossary);

        applyGlossaryFilters();
    }

    $('#add_glossary').on('click', function (e) {
        e.preventDefault();
        appendGlossaryRow({});
    });

    function syncGlossaryLanguageDropdowns() {
        var targetCodes = ($('input[name="target_languages"]').val() || '').trim().split(',').map(function (s) { return s.trim(); }).filter(Boolean);
        var $filter = $('#glossary_filter_language');
        if ($filter.length) {
            var currentFilter = $filter.val();
            $filter.empty().append('<option value="">Show all</option><option value="__all__">All languages</option>');
            for (var id in languages) {
                if (languages.hasOwnProperty(id) && targetCodes.indexOf(languages[id].code2) !== -1) {
                    $filter.append('<option value="' + languages[id].code2 + '">' + (languages[id].title_en || languages[id].code2) + '</option>');
                }
            }
            if (currentFilter && $filter.find('option[value="' + currentFilter + '"]').length) $filter.val(currentFilter);
        }
        $('#glossary_wrapper').children('.glossary').each(function () {
            var $select = $(this).find('select.target_language');
            if (!$select.length) return;
            var currentVal = $select.val();
            $select.empty().append('<option value="">All languages</option>');
            for (var id in languages) {
                if (languages.hasOwnProperty(id) && targetCodes.indexOf(languages[id].code2) !== -1) {
                    $select.append('<option value="' + languages[id].code2 + '">' + (languages[id].title_en || languages[id].code2) + '</option>');
                }
            }
            if (currentVal && $select.find('option[value="' + currentVal + '"]').length) $select.val(currentVal);
            $(this).data('target-language', $select.val() || '');
        });
    }

    syncGlossaryLanguageDropdowns();
    applyGlossaryFilters();

    function getGlossaryRuleFromRow($row) {
        var $ruleEl = $row.find('select.rule');
        var rule = $ruleEl.length ? ($ruleEl.val() || '') : '';
        if (!rule && $ruleEl.length && $ruleEl.data('dropdown')) {
            try { rule = $ruleEl.dropdown('get value') || ''; } catch (e) {}
        }
        rule = (rule === 'replace' || rule === 'prevent') ? rule : 'prevent';

        var $sourceInput = $row.find('input.source_text');
        var $translateInput = $row.find('input.translate_text');
        var source = ($sourceInput.val() || '').trim();
        var translate = ($translateInput.val() || '').trim();
        var lang = $row.data('target-language');
        if (lang === undefined || lang === null) {
            var $langEl = $row.find('select.target_language');
            lang = $langEl.length ? ($langEl.val() || '') : '';
            if (!lang && $langEl.length && $langEl.data('dropdown')) {
                try { lang = $langEl.dropdown('get value') || ''; } catch (e) {}
            }
            lang = (lang || '').trim();
        } else {
            lang = String(lang).trim();
        }
        return { rule: rule, source_text: source, translate_text: translate, target_language: lang };
    }

    function getGlossaryRulesForExport() {
        var rules = [];
        $('#glossary_wrapper').children('.glossary').each(function () {
            var data = getGlossaryRuleFromRow($(this));
            if (data.rule && data.source_text) {
                rules.push(data);
            }
        });
        return rules;
    }

    function getGlossaryRulesForSave() {
        var rules = [];
        var $wrapper = $('#glossary_wrapper');
        var $rows = $wrapper.children('.glossary');
        var $rowsAny = $wrapper.find('.glossary');
        if ($rows.length === 0 && $rowsAny.length > 0) {
            $rows = $rowsAny;
        }
        $rows.each(function (i) {
            var $row = $(this);
            var data = getGlossaryRuleFromRow($row);
            if (data.rule && data.source_text) {
                var gl = { rule: data.rule, source_text: data.source_text, translate_text: data.translate_text, target_language: data.target_language };
                var id = $row.find('input.glossary_id').val();
                if (id) gl.glossary_id = id;
                rules.push(gl);
            }
        });
        return rules;
    }

    function escapeCsvField(val) {
        var s = String(val == null ? '' : val);
        if (s.indexOf('"') !== -1) {
            s = s.replace(/"/g, '""');
        }
        if (s.indexOf(',') !== -1 || s.indexOf('"') !== -1 || s.indexOf('\n') !== -1 || s.indexOf('\r') !== -1) {
            s = '"' + s + '"';
        }
        return s;
    }

    function parseCsvLine(line) {
        var fields = [];
        var i = 0;
        while (i < line.length) {
            if (line[i] === '"') {
                i++;
                var f = '';
                while (i < line.length) {
                    if (line[i] === '"' && (i + 1 >= line.length || line[i + 1] !== '"')) {
                        i++;
                        break;
                    }
                    if (line[i] === '"' && line[i + 1] === '"') {
                        f += '"';
                        i += 2;
                        continue;
                    }
                    f += line[i];
                    i++;
                }
                fields.push(f);
                if (line[i] === ',') i++;
            } else {
                var start = i;
                while (i < line.length && line[i] !== ',') i++;
                fields.push(line.slice(start, i).replace(/^\s+|\s+$/g, ''));
                if (line[i] === ',') i++;
            }
        }
        return fields;
    }

    function parseGlossaryCsv(csvText) {
        var lines = csvText.split(/\r\n|\r|\n/);
        var rows = [];
        for (var i = 0; i < lines.length; i++) {
            var line = lines[i].trim();
            if (!line) continue;
            var fields = parseCsvLine(line);
            if (fields.length < 4) {
                while (fields.length < 4) fields.push('');
            }
            rows.push({
                rule: (fields[0] || '').trim().toLowerCase() === 'replace' ? 'replace' : 'prevent',
                source_text: (fields[1] || '').trim(),
                translate_text: (fields[2] || '').trim(),
                target_language: (fields[3] || '').trim().toLowerCase() === 'all' ? '' : (fields[3] || '').trim()
            });
        }
        return rows;
    }

    $('#glossary_export').on('click', function () {
        var rules = getGlossaryRulesForExport();
        var header = 'rule,source_text,translate_text,target_language';
        var rows = [header];
        for (var i = 0; i < rules.length; i++) {
            var r = rules[i];
            var targetLang = (r.target_language && r.target_language.trim()) ? r.target_language.trim() : 'all';
            var translateVal = (r.rule === 'prevent') ? '' : (r.translate_text || '');
            var targetVal = (r.rule === 'prevent') ? '' : targetLang;
            rows.push([
                escapeCsvField(r.rule),
                escapeCsvField(r.source_text),
                escapeCsvField(translateVal),
                escapeCsvField(targetVal)
            ].join(','));
        }
        var csv = rows.join('\r\n');
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'conveythis-glossary-' + new Date().toISOString().slice(0, 10) + '.csv';
        a.click();
        URL.revokeObjectURL(url);
    });

    $('#glossary_import').on('click', function () {
        $('#glossary_import_file').click();
    });

    $('#glossary_import_file').on('change', function () {
        var input = this;
        var file = input.files && input.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function () {
            var text = reader.result;
            var added = 0;
            var invalid = 0;
            var skippedLang = 0;
            var isCsv = /\.csv$/i.test(file.name);
            var availableLangs = ($('input[name="target_languages"]').val() || '').split(',').map(function (s) { return s.trim().toLowerCase(); }).filter(Boolean);

            function isTargetLanguageAllowed(targetLang) {
                if (!targetLang) return true;
                var code = targetLang.trim().toLowerCase();
                return availableLangs.indexOf(code) !== -1;
            }

            if (isCsv) {
                try {
                    var rows = parseGlossaryCsv(text);
                    if (rows.length > 0 && rows[0].source_text === 'source_text' && rows[0].translate_text === 'translate_text') {
                        rows.shift();
                    }
                    for (var c = 0; c < rows.length; c++) {
                        var row = rows[c];
                        if (!row.source_text) {
                            invalid++;
                            continue;
                        }
                        if (!isTargetLanguageAllowed(row.target_language)) {
                            skippedLang++;
                            continue;
                        }
                        appendGlossaryRow({
                            source_text: row.source_text,
                            rule: row.rule,
                            translate_text: row.translate_text || '',
                            target_language: row.target_language || ''
                        });
                        added++;
                    }
                    var skipMsg = (invalid > 0 ? ' Skipped ' + invalid + ' invalid.' : '') + (skippedLang > 0 ? ' Skipped ' + skippedLang + ' (language not available).' : '');
                    if (added > 0) {
                        alert('Imported ' + added + ' rule(s) from CSV.' + skipMsg);
                    } else if (invalid > 0 || skippedLang > 0) {
                        alert('No rules imported.' + skipMsg);
                    } else {
                        alert('No data rows to import. Expected header: rule,source_text,translate_text,target_language');
                    }
                } catch (err) {
                    console.error('[Glossary Import] CSV parse error:', err);
                    alert('Invalid CSV file: ' + (err.message || 'parse error'));
                }
                input.value = '';
                return;
            }

            try {
                var data = JSON.parse(text);
                if (!Array.isArray(data)) {
                    alert('Invalid file: expected a JSON array of glossary rules.');
                    input.value = '';
                    return;
                }
                for (var i = 0; i < data.length; i++) {
                    var item = data[i];
                    if (!item || typeof item.source_text === 'undefined' || !item.source_text) {
                        invalid++;
                        continue;
                    }
                    var itemTargetLang = item.target_language != null ? String(item.target_language).trim() : '';
                    if (!isTargetLanguageAllowed(itemTargetLang)) {
                        skippedLang++;
                        continue;
                    }
                    var rule = (item.rule === 'replace' || item.rule === 'prevent') ? item.rule : 'prevent';
                    appendGlossaryRow({
                        source_text: String(item.source_text).trim(),
                        rule: rule,
                        translate_text: item.translate_text != null ? String(item.translate_text).trim() : '',
                        target_language: itemTargetLang
                    });
                    added++;
                }
                var skipMsgJson = (invalid > 0 ? ' Skipped ' + invalid + ' invalid.' : '') + (skippedLang > 0 ? ' Skipped ' + skippedLang + ' (language not available).' : '');
                if (added > 0) {
                    alert('Imported ' + added + ' rule(s).' + skipMsgJson);
                } else if (invalid > 0 || skippedLang > 0) {
                    alert('No rules imported.' + skipMsgJson);
                } else {
                    alert('No rules to import.');
                }
            } catch (err) {
                alert('Invalid file. Use CSV (rule,source_text,translate_text,target_language) or JSON array.');
            }
            input.value = '';
        };
        reader.readAsText(file);
    });


    $(document).on('input', '#link_enter', function () {
        var inputVal = $(this).val();
        var validPattern = /^\/(?!http:\/\/|https:\/\/).*$/;

        if (!validPattern.test(inputVal)) {
            $(this).val('');
            alert('Note: Please provide the link in the format (/404.html or /system/link) without using (https:// or http://), and also without the domain name (temp_domain.com).');
        }
    });

    $('#add_system_link').on('click', function (e) {

        e.preventDefault();

        let $systemLink = $(
            '<div class="system_link position-relative w-100"> ' +
            '<input type="hidden" class="system_link_id" value=""/> ' +
            '<button type="submit" name="submit" class="conveythis-delete-page"></button> ' +
            '<div class="row w-100 mb-2">' +
            '<div class="ui input w-100">' +
            '<input' +
            ' id="link_enter" type="text" class="link_text w-100 conveythis-input-text" placeholder="Enter link (/404.html or /path/path...)" ' +
            ' value="" >' +
            '</div></div></div>'
        );

        $systemLink.find('.conveythis-delete-page').on('click', function (e) {
            e.preventDefault();
            $(this).parent().remove();
        });

        $("#system_link_wrapper").append($systemLink);
        $('.ui.dropdown').dropdown()

    });

    $('#conveythis_clear_all_cache').on('click', function (e) {
        e.preventDefault()
        var ajax_url = $(this).data('href');
        var apiKeyVal = $('#conveythis_api_key').val()
        var data = {
            'api_key': apiKeyVal,
            'conveythis_clear_all_cache': true
        };

        $.ajax({
            url: ajax_url,
            type: 'post',
            data: data,
            dataType: 'json',
            success: function (response) {
                if (response.clear) {
                    $('#conveythis_confirmation_message_clear_all_cahce').hide();
                }
            }
        });
    });


    $('#conveythis_dismiss_all_cache').on('click', function (e) {
        e.preventDefault()
        var ajax_url = $(this).data('href');
        var data = {
            'dismiss': true
        };

        $.ajax({
            url: ajax_url,
            type: 'post',
            data: data,
            dataType: 'json',
            success: function (response) {
                if (response.clear) {
                    $('#conveythis_confirmation_message_clear_all_cahce').hide();
                }
            }
        });
    });

    $('#clear_translate_cache').on('click', function (e) {
        jQuery.ajax({
            url: 'options.php',
            method: 'POST',
            data: {'clear_translate_cache': true},
            beforeSend: function () {
                $('.spinner-cache').removeClass('d-none')
                $('.clear-success').addClass('d-none')
                $('.clear-failure').addClass('d-none')
            },
            success: function (result) {
                $('.spinner-cache').addClass('d-none')

                if (result.clear_cache_translate) {
                    $('.clear-success').removeClass('d-none')
                } else {
                    $('.clear-failure').removeClass('d-none')
                }
            }
        })
    });

    $(document).on('change', '#glossary_wrapper select.rule', function (e) {
        e.preventDefault();
        var $row = $(this).closest('.glossary');
        var $input = $row.find('input.translate_text');
        if (this.value === 'prevent') {
            $input.prop('disabled', true);
        } else {
            $input.prop('disabled', false);
        }
    });

    function syncGlossaryTranslateInputs() {
        var $rows = $('#glossary_wrapper').children('.glossary');
        $rows.each(function (i) {
            var $row = $(this);
            var $ruleSelect = $row.find('select.rule');
            var rule = $ruleSelect.val();
            var $input = $row.find('input.translate_text');
            var disabled = (rule !== 'replace');
            $input.prop('disabled', disabled);
        });
    }

    function initGlossaryRuleDropdowns() {
        syncGlossaryTranslateInputs();
    }

    syncGlossaryTranslateInputs();
    setTimeout(initGlossaryRuleDropdowns, 500);
    $(document).on('shown.bs.tab', 'button[data-bs-target="#v-pills-glossary"], a[data-bs-target="#v-pills-glossary"]', function () {
        syncGlossaryLanguageDropdowns();
        initGlossaryRuleDropdowns();
        applyGlossaryFilters();
    });

    $('#add_exlusion_block').on('click', function (e) {
        e.preventDefault();
        let $exclusion_block = $('<div class="exclusion_block position-relative w-100 pe-4">\n' +
            '                        <button class="conveythis-delete-page"></button>\n' +
            '                        <div class="ui input">\n' +
            '                            <input type="text" class="form-control id_value w-100" data-type="id" placeholder="Enter id">\n' +
            '                        </div>\n' +
            '                    </div>');

        $exclusion_block.find('.conveythis-delete-page').on('click', function (e) {
            e.preventDefault();
            $(this).parent().remove();
        });

        $("#exclusion_block_wrapper").append($exclusion_block);

    });

    $('#add_exlusion_block_class').on('click', function (e) {
        e.preventDefault();

        let $exclusion_block = $('<div class="exclusion_block position-relative w-100 pe-4">\n' +
            '    <button class="conveythis-delete-page"></button>\n' +
            '    <div class="ui input">\n' +
            '        <input type="text" class="form-control id_value w-100" data-type="class" placeholder="Enter class">\n' +
            '    </div>\n' +
            '</div>');

        $exclusion_block.find('.conveythis-delete-page').on('click', function (e) {
            e.preventDefault();
            $(this).parent().remove();
        });

        $("#exclusion_block_classes_wrapper").append($exclusion_block);
    });

    $('.widget-trigger [name="style_widget"]').on('change', function (e) {
        const customizePlugin = $(".customize-view-button-wrapper")
        customizePlugin.removeClass('widget-popup widget-dropdown widget-list');
        customizePlugin.addClass('widget-' + $(this).val());
    });

    function showPositionType(type) {

        if (type == 'custom') {
            $('#position-fixed').fadeOut();
            $('#position-custom').fadeIn();
        } else {
            $('#position-custom').fadeOut();
            $('#position-fixed').fadeIn();
        }
    }

    function showUrlStructureType(type) {

        if (type == 'subdomain') {
            $('#dns-setup').fadeIn();
            showDnsRecords();
        } else {
            $('#dns-setup').fadeOut();
        }
    }

    function showDnsRecords() {

        let targetLanguages = $('input[name=target_languages]').val();
        if (targetLanguages) {
            targetLanguages = targetLanguages.split(",");
            $("#dns-setup-records").html("");
            for (language_id in languages) {
                if (targetLanguages.includes(languages[language_id].code2)) {
                    let row = "<tr data-domain='" + languages[language_id].code2 + "." + location.hostname + "'><td>" + languages[language_id].title_en + "</td><td>" + languages[language_id].code2 + "." + location.hostname + "</td><td>dns2.conveythis.com</td></tr>";
                    $("#dns-setup-records").append(row);
                }
            }
        }
    }

    showDnsRecords();

    $('input[name=style_position_type]').change(function () {
        showPositionType(this.value);
    });

    $('input[name=url_structure]').change(function () {
        showUrlStructureType(this.value);
    });

    $('#dns-check').click(function (e) {
        e.preventDefault();

        $.ajax({
            url: conveythis_plugin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'check_dns',
                _ajax_nonce: conveythis_plugin_ajax.nonce
            },
            beforeSend: function () {
                $("#dns-setup-records .dns-status").remove();
                $("#dns-setup #dns-check").hide();
                $("#dns-setup #dns-loader").show();
                $("#dns-setup .message").empty();
            },
            success: function (response) {
                let dnsCheck = true;
                for (const [domain, cnameList] of Object.entries(response.data.records)) {
                    const $row = $(`tr[data-domain="${domain}"]`);

                    let icon = '❌';

                    if (Array.isArray(cnameList)) {
                        const valid = cnameList.some(cname =>
                            cname.includes('dns1.conveythis.com') || cname.includes('dns2.conveythis.com')
                        );

                        if (valid) {
                            icon = '✔️';
                        } else {
                            dnsCheck = false;
                        }
                    } else {
                        dnsCheck = false;
                    }


                    $row.find('td:last').append(` <span class="dns-status">${icon}</span>`);
                }

                if (dnsCheck) {
                    $("#dns-setup .message").html("✔️ DNS successfully connected!");
                } else {
                    $("#dns-setup .message").html("❌ Please check your settings in DNS manager");
                }
            },
            complete: function () {
                $("#dns-setup #dns-check").show();
                $("#dns-setup #dns-loader").hide();
            }
        });
    });

    $('input[name=target_languages]').change(function () {
        let targetLanguagesTranslations = $('input[name="target_languages_translations"]').val();
        targetLanguagesTranslations = targetLanguagesTranslations ? JSON.parse(targetLanguagesTranslations) : {};
        let targetLanguages = this.value.split(",");
        $("#target_languages_translations").html("");
        $("#default_language_list").html('<div class="item" data-value="">No value</div>');
        for (language_id in languages) {
            let langCode = languages[language_id].code2;
            if (targetLanguages.includes(langCode)) {
                let row = "<tr><td>" + "<div class='ui input'><input type='text' language_id='" + langCode + "' value='" + (targetLanguagesTranslations[langCode] ? targetLanguagesTranslations[langCode] : langCode) + "' placeholder='Alias for " + languages[language_id].title_en + "' /></div>" + "</td></tr>";
                $("#target_languages_translations").append(row);
                $("#default_language_list").append('<div class="item" data-value="' + langCode + '">' + languages[language_id].title_en + '</div>');
            }
        }
    });

    function checkValidation() {

        let validation = true;
        let apiKey = $('#conveythis_api_key')
        let sourceLanguage = $('input[name="source_language"]')
        let targetLanguage = $('input[name="target_languages"]')
        if (!apiKey.val()) {
            $('#apiKey .validation-label').show()
            $('#apiKey .input').addClass('validation-icon')
            apiKey.addClass('validation-failed')
            validation = false;
        } else {
            $('#apiKey .validation-label').hide()
            $('#apiKey .input').removeClass('validation-icon')
            apiKey.removeClass('validation-failed')
        }

        if (!sourceLanguage.val() && !sourceLanguage.hasClass('first-submit')) {
            $('#sourceLanguage .dropdown').addClass('validation-icon')
            $('#sourceLanguage .validation-label').show()
            sourceLanguage.parent().addClass('validation-failed')
            validation = false;
        } else {
            $('#sourceLanguage .dropdown').removeClass('validation-icon')
            $('#sourceLanguage .validation-label').hide()
            sourceLanguage.parent().removeClass('validation-failed')
        }
        if (!targetLanguage.val() && !targetLanguage.hasClass('first-submit')) {
            $('#targetLanguages .dropdown').addClass('validation-icon')
            $('#targetLanguages .validation-label').show()
            targetLanguage.parent().addClass('validation-failed')
            validation = false;
        } else {
            $('#targetLanguages .dropdown').removeClass('validation-icon')
            $('#targetLanguages .validation-label').hide()
            targetLanguage.parent().removeClass('validation-failed')
        }

        return validation;
    }

    $('.conveythis-widget-option-form, #login-form-settings').submit(function (e) {
        let apiKey = $("#conveythis_api_key").val();
        /*
        let validation = checkValidation();
        if (validation === false) {
            e.preventDefault();
        }
         */

        let targetLanguagesTranslations = {};
        let $tLangTranslations = $("#target_languages_translations input[language_id]");
        for (let t of $tLangTranslations) {
            let languageTranslation = t.value.trim();
            if (languageTranslation.indexOf('/') > -1) {
                alert('Translation cannot contain slash: ' + languageTranslation);
                return false;
            }
            if (languageTranslation) {
                targetLanguagesTranslations[t.getAttribute('language_id')] = languageTranslation;
            }
        }

        $('input[name="target_languages_translations"]').val(JSON.stringify(targetLanguagesTranslations));

        let exclusions = [];
        $('div.exclusion').each(function () {
            let rule = $(this).find('.rule select').val();
            let pageUrl = $(this).find('input.page_url').val().trim();
            if (rule && pageUrl) {
                let ex = {rule: rule, page_url: pageUrl};
                let exclusionId = $(this).find('input.exclusion_id').val();
                if (exclusionId) {
                    ex.id = exclusionId;
                }
                exclusions.push(ex);
            }
        });
        $('input[name="exclusions"]').val(JSON.stringify(exclusions));

        let glossaryRules = getGlossaryRulesForSave();
        $('#glossary_data').val(JSON.stringify(glossaryRules));

        let exclusion_blocks = [];
        $('div.exclusion_block').each(function () {
            let idValue = $(this).find('input.id_value').val().trim();
            if (idValue) {
                let type = $(this).find('input.id_value').data('type');
                let exBlock = {id_value: idValue, type: type};
                let exclusionBlockId = $(this).find('input.exclusion_block_id').val();
                if (exclusionBlockId) {
                    exBlock.id = exclusionBlockId;
                }
                exclusion_blocks.push(exBlock);
            }
        });
        $('input[name="exclusion_blocks"]').val(JSON.stringify(exclusion_blocks));

        let system_links = [];
        $('div.system_link').each(function () {

            let link = $(this).find('input.link_text').val();

            if (!!link) {
                let sl = {link: link};
                let linkId = $(this).find('input.system_link_id').val();
                if (!!linkId) {
                    sl.link_id = linkId;
                }
                system_links.push(sl);
            }
        })
        $('input[name="conveythis_system_links"]').val(JSON.stringify(system_links));
    });

    function prepareSettingsBeforeSave() {
        let targetLanguagesTranslations = {};
        $("#target_languages_translations input[language_id]").each(function () {
            const val = this.value.trim();
            if (val.includes('/')) {
                alert('Translation cannot contain slash: ' + val);
                return false;
            }
            if (val) {
                targetLanguagesTranslations[this.getAttribute('language_id')] = val;
            }
        });
        $('input[name="target_languages_translations"]').val(JSON.stringify(targetLanguagesTranslations));

        let exclusions = [];
        $('div.exclusion').each(function () {
            let rule = $(this).find('.rule select').val();
            let pageUrl = $(this).find('input.page_url').val().trim();
            if (rule && pageUrl) {
                let ex = {rule, page_url: pageUrl};
                let id = $(this).find('input.exclusion_id').val();
                if (id) ex.id = id;
                exclusions.push(ex);
            }
        });
        $('input[name="exclusions"]').val(JSON.stringify(exclusions));

        var $glossaryRows = $('#glossary_wrapper').children('.glossary');
        $glossaryRows.each(function (i) {
            var $row = $(this);
            $row.find('select.rule, select.target_language').each(function () {
                var $sel = $(this);
                if ($sel.data('dropdown')) {
                    try {
                        var v = $sel.dropdown('get value');
                        if (v !== undefined && v !== null) $sel.val(v);
                    } catch (e) {}
                }
            });
        });
        let glossary = getGlossaryRulesForSave();
        $('#glossary_data').val(JSON.stringify(glossary));

        // Prepare style_change_language and style_change_flag arrays
        // CRITICAL: Sync dropdown values to hidden inputs before collecting

        // First, ensure all dropdown values are synced to hidden inputs
        $('.style-language').each(function () {
            let $row = $(this);
            let $languageDropdown = $row.find('.ui.dropdown.change_language');
            let $flagDropdown = $row.find('.ui.dropdown.change_flag');
            let $languageInput = $row.find('input[name="style_change_language[]"]');
            let $flagInput = $row.find('input[name="style_change_flag[]"]');

            // Get current dropdown values
            let langValue = $languageDropdown.dropdown('get value');
            let flagValue = $flagDropdown.dropdown('get value');

            // Update hidden inputs with current dropdown values
            if ($languageInput.length && langValue) {
                $languageInput.val(langValue);
            }
            if ($flagInput.length && flagValue) {
                $flagInput.val(flagValue);
            }
        });

        // Now collect from hidden inputs
        let style_change_language = [];
        let style_change_flag = [];

        $('.style-language').each(function () {
            let $row = $(this);
            let $languageInput = $row.find('input[name="style_change_language[]"]');
            let $flagInput = $row.find('input[name="style_change_flag[]"]');

            // Get values from hidden inputs
            let langValue = $languageInput.length ? $languageInput.val() : '';
            let flagValue = $flagInput.length ? $flagInput.val() : '';

            // Only add if language is set
            if (langValue && langValue.trim() !== '') {
                style_change_language.push(langValue.trim());
                style_change_flag.push(flagValue ? flagValue.trim() : '');
            }
        });


        let exclusion_blocks = [];
        $('div.exclusion_block').each(function () {
            let idVal = $(this).find('input.id_value').val().trim();
            if (idVal) {
                let type = $(this).find('input.id_value').data('type');
                let ex = {id_value: idVal, type: type};
                let id = $(this).find('input.exclusion_block_id').val();
                if (id) ex.id = id;
                exclusion_blocks.push(ex);
            }
        });
        $('input[name="exclusion_blocks"]').val(JSON.stringify(exclusion_blocks));

        let system_links = [];
        $('div.system_link').each(function () {
            let link = $(this).find('input.link_text').val();
            if (link) {
                let sl = {link};
                let id = $(this).find('input.system_link_id').val();
                if (id) sl.link_id = id;
                system_links.push(sl);
            }
        });
        $('input[name="conveythis_system_links"]').val(JSON.stringify(system_links));
    }

    $('input[name=target_languages]').change();

    function hideTargetLanguage() {
        $('.dropdown-target-languages .item').show();
        let currentLanguage = $('.dropdown-current-language').dropdown('get value');
        if (currentLanguage != '') {
            $('.target-language-' + currentLanguage).hide();
        }
    }

    var _alias = "";

    var inputElementClearCache = document.getElementById('conveythis_clear_cache');

    if (inputElementClearCache) {
        inputElementClearCache.addEventListener('input', function () {

            var value = this.value;

            if (value === '') return;

            var hour = 720;
            if (_alias == "grand")
                hour = 7200;

            if (!/^\d+$/.test(value) || (value < 0 || value > hour)) {
                this.value = this.dataset.lastValidValue || '';
            } else {
                this.dataset.lastValidValue = value;
            }
        });
        inputElementClearCache.addEventListener('blur', function () {
            if (this.value.trim() === '') {
                this.value = '0';
            } else if (/^0[1-9]+/.test(this.value)) {
                this.value = parseInt(this.value, 10).toString();
            }
        });

    }

    // Function to update language dropdowns to disable already-selected languages
    function updateLanguageDropdownAvailability() {

        // Get all currently selected language IDs (excluding empty values)
        let selectedLanguages = [];
        $('.style-language').each(function() {
            let $languageInput = $(this).find('input[name="style_change_language[]"]');
            let langValue = $languageInput.length ? $languageInput.val() : '';
            if (langValue && langValue.trim() !== '') {
                selectedLanguages.push(langValue.trim());
            }
        });

        // Update all language dropdowns
        $('.ui.dropdown.change_language').each(function() {
            let $currentDropdown = $(this);
            let $currentRow = $currentDropdown.closest('.style-language');
            let $currentInput = $currentRow.find('input[name="style_change_language[]"]');
            let currentValue = $currentInput.length ? $currentInput.val() : '';

            // Enable/disable items in this dropdown
            $currentDropdown.find('.menu .item').each(function() {
                let $item = $(this);
                let itemValue = $item.attr('data-value');

                // If this language is selected in another row, disable it (unless it's the current row's selection)
                if (selectedLanguages.includes(itemValue) && itemValue !== currentValue) {
                    $item.addClass('disabled');
                } else {
                    $item.removeClass('disabled');
                }
            });
        });
    }

    // Sort flags by languages
    function sortFlagsByLanguage() {
        $('.ui.dropdown.change_language').dropdown({
            onChange: function (value) {

                // Update the hidden input for language
                let $languageInput = $(this).closest('.row').find('input[name="style_change_language[]"]');
                if ($languageInput.length) {
                    $languageInput.val(value);
                }

                // Update availability of languages in all dropdowns
                updateLanguageDropdownAvailability();

                let $dropdown = $(this).closest('.row').find('.ui.dropdown.change_flag');

                // Check if flag_codes exists for this language
                if (languages[value] && languages[value]['flag_codes']) {
                    let flagCodes = languages[value]['flag_codes'];

                    // Clear existing menu items and text
                    $dropdown.find('.menu').empty();
                    $dropdown.find('.text').text('Select Flag');
                    $dropdown.find('input[type="hidden"]').val('');

                    // Populate with new flag options
                    $.each(flagCodes, function (code, title) {
                        let newItem = $('<div class="item" data-value="' + code + '">\
                                            <div class="ui image" style="height: 28px; width: 30px; background-position: 50% 50%;\
                                                background-size: contain; background-repeat: no-repeat;\
                                                background-image: url(\'//cdn.conveythis.com/images/flags/svg/' + code + '.svg\')"></div>\
                                            ' + title + '\
                                        </div>');
                        $dropdown.find('.menu').append(newItem);
                    });

                    // Destroy and reinitialize dropdown to ensure it recognizes new items
                    try {
                        $dropdown.dropdown('destroy');
                    } catch(e) {
                        // Dropdown may not be initialized yet
                    }
                    $dropdown.dropdown();
                } else {
                    // If no flag_codes, clear the dropdown
                    $dropdown.find('.menu').empty();
                    $dropdown.find('.text').text('Select Flag');
                    $dropdown.find('input[type="hidden"]').val('');
                    try {
                        $dropdown.dropdown('destroy');
                    } catch(e) {
                        // Dropdown may not be initialized yet
                    }
                    $dropdown.dropdown();
                }
            },
            onRemove: function (value) {
                // When language is cleared/removed

                // Clear the hidden input
                let $languageInput = $(this).closest('.row').find('input[name="style_change_language[]"]');
                if ($languageInput.length) {
                    $languageInput.val('');
                }

                // Update availability - re-enable this language in other dropdowns
                updateLanguageDropdownAvailability();
            }
        });

        // Handle flag dropdown changes
        $('.ui.dropdown.change_flag').dropdown({
            onChange: function (value) {

                // Update the hidden input for flag
                let $flagInput = $(this).closest('.row').find('input[name="style_change_flag[]"]');
                if ($flagInput.length) {
                    $flagInput.val(value);
                }
            },
        });
    }

    // Initialize dropdowns with saved values
    function initializeFlagDropdowns() {
        $('.style-language').each(function() {
            let $row = $(this);
            let $languageDropdown = $row.find('.ui.dropdown.change_language');
            let $flagDropdown = $row.find('.ui.dropdown.change_flag');
            let $languageInput = $row.find('input[name="style_change_language[]"]');
            let $flagInput = $row.find('input[name="style_change_flag[]"]');

            // Initialize language dropdown if value exists
            if ($languageInput.length && $languageInput.val()) {
                let languageValue = $languageInput.val();

                // Set the language dropdown value
                if (languages[languageValue]) {
                    $languageDropdown.dropdown('set selected', languageValue);

                    // Populate flags if flag_codes exists
                    if (languages[languageValue]['flag_codes']) {
                        let flagCodes = languages[languageValue]['flag_codes'];

                        // Clear existing menu items
                        $flagDropdown.find('.menu').empty();
                        $flagDropdown.find('.text').text('Select Flag');

                        $.each(flagCodes, function (code, title) {
                            let newItem = $('<div class="item" data-value="' + code + '">\
                                <div class="ui image" style="height: 28px; width: 30px; background-position: 50% 50%;\
                                    background-size: contain; background-repeat: no-repeat;\
                                    background-image: url(\'//cdn.conveythis.com/images/flags/svg/' + code + '.svg\')"></div>\
                                ' + title + '\
                            </div>');
                            $flagDropdown.find('.menu').append(newItem);
                        });

                        // Destroy and reinitialize dropdown to ensure it recognizes new items
                        try {
                            $flagDropdown.dropdown('destroy');
                        } catch(e) {
                            // Dropdown may not be initialized yet
                        }
                        $flagDropdown.dropdown();

                        // Set flag value if it exists
                        if ($flagInput.length && $flagInput.val()) {
                            let flagValue = $flagInput.val();
                            $flagDropdown.dropdown('set selected', flagValue);
                        }
                    }
                }
            }
        });

        // Update language availability after initialization
        updateLanguageDropdownAvailability();
    }

    function getUserPlan() {
        try {
            let apiKey = $("#conveythis_api_key").val();

            if (apiKey) {
                jQuery.ajax({
                    url: "https://api.conveythis.com/admin/account/plan/api-key/" + apiKey + "/",
                    success: function (result) {
                        if (result.data && result.data.languages) {
                            let plan_name = ""
                            if(result.data.meta.alias){
                                plan_name = result.data.meta.alias
                                let plan_name_formatted = plan_name.replace(/_/g, ' '); // pro_trial -> pro trial
                                $("#plan_name").html(plan_name_formatted[0].toUpperCase() + plan_name_formatted.slice(1)) // pro trial -> Pro trial
                                $("#plan_info").removeClass("d-none")

                                if(result.data.trial_expires_at  && plan_name === 'pro_trial'  ){
                                    let trial_expires_at = result.data.trial_expires_at
                                    let expiryDate = new Date(result.data.trial_expires_at);
                                    let currentDate = new Date();

                                    let diffInMs = expiryDate - currentDate;
                                    let remaining_days = Math.ceil(diffInMs / (1000 * 60 * 60 * 24));
                                    let trial_days_message = ' days left. Fully test ConveyThis on the PRO trial plan. When trial expires, you can choose to switch to the <a href="https://app.conveythis.com/dashboard/pricing/" target="_blank">FREE plan or upgrade</a>';
                                    if(remaining_days < 0){
                                        trial_days_message = '<span class="fw-bold"> Your Pro Trial plan has expired. Click <a href="https://app.conveythis.com/dashboard/pricing/" target="_blank">here</a> to upgrade your plan. </span>'
                                        $("#trial_days_info").removeClass("alert-warning")
                                        $("#trial_days_info").addClass("alert-danger")
                                        remaining_days = 0
                                        $("#settings_content").addClass('content_disabled')
                                    }
                                    else{
                                        $("#trial_days").html(remaining_days)
                                    }


                                    $("#trial_days_message").html(trial_days_message)
                                    $("#trial_days_info").removeClass("d-none")
                                    /*
                                                                        if (remainingDays > 0) {
                                                                            $('#trial-days').text(remainingDays);
                                                                            $('#trial-period').text(' days');
                                                                            $('#conveythis_trial_period').css('display', 'block');
                                                                        } else if (remainingDays === 0) {
                                                                            $('#trial-days').text('Less than 24');
                                                                            $('#trial-period').text('hours');
                                                                            $('#conveythis_trial_period').css('display', 'block');
                                                                        } else {
                                                                            console.log("Your trial has expired.");
                                                                        }

                                     */
                                }



                            }
                            //console.log("### plan name:" + plan_name)
                            const maxLanguages = result.data.languages;
                            $('.dropdown-target-languages').dropdown({
                                maxSelections: maxLanguages,
                                message: {
                                    maxSelections: 'Need more languages? <a href="//app.conveythis.com/dashboard/pricing" target="_blank">Upgrade your plan</a>.'
                                },
                                onChange: function () {
                                    conveythisSettings.view();
                                    showDnsRecords();
                                }
                            });
                            hideTargetLanguage();
                            let tempLanguages = $('.dropdown-target-languages').dropdown('get value');
                            if (tempLanguages) {
                                try {
                                    let tempLanguagesArray = tempLanguages.split(",");
                                    if (tempLanguagesArray.length > maxLanguages) {
                                        let allowedLanguages = [];
                                        for (let i = 0; i < maxLanguages; i++)
                                            allowedLanguages.push(tempLanguagesArray[i]);
                                        let allowedLanguagesStr = allowedLanguages.join(",");
                                        $('.dropdown-target-languages').dropdown('set value', allowedLanguagesStr);

                                        setTimeout(function () {
                                            $('.dropdown-target-languages').dropdown('set selected', allowedLanguagesStr);
                                        }, 200);
                                    }
                                } catch (e) {
                                }
                            }

                            if (!result.data.meta.alias.includes('free')) {
                                $('.hide-paid').remove();
                            } else {
                                $('.paid-function input').prop('disabled', true)
                                $('.paid-function button').prop('disabled', true)
                            }

                            if (result.data.meta.alias.includes('free')) {
                                $('input[name=hide_conveythis_logo][value = 1]').prop('disabled', true);
                                $('input[name=hide_conveythis_logo][value = 0]').prop('checked', true);
                            }

                            _alias = result.data.meta.alias;

                            if (
                                result.data.meta.alias == "free" ||
                                result.data.meta.alias == "free_plan" ||
                                result.data.meta.alias == "starter") {
                                $('input[name=url_structure][value=regular]').prop('checked', true);
                                $('input[name=url_structure][value=subdomain]').prop('disabled', true);
                                $('#dns-plan-error').show();
                                $('#dns-setup').hide();
                            }

                            if (
                                result.data.meta.alias != "enterprise" &&
                                result.data.meta.alias != "grand"
                            ) {
                                $('#conveythis_clear_cache').prop('disabled', true);
                            }

                            /*
                                                        const expiryDate = new Date(result.data.trial_expires_at);
                                                        const currentDate = new Date();

                                                        const diffInMs = expiryDate - currentDate;
                                                        const remainingDays = Math.ceil(diffInMs / (1000 * 60 * 60 * 24));

                                                        if (remainingDays > 0) {
                                                            $('#trial-days').text(remainingDays);
                                                            $('#trial-period').text(' days');
                                                            $('#conveythis_trial_period').css('display', 'block');
                                                        } else if (remainingDays === 0) {
                                                            $('#trial-days').text('Less than 24');
                                                            $('#trial-period').text('hours');
                                                            $('#conveythis_trial_period').css('display', 'block');
                                                        } else {
                                                            console.log("Your trial has expired.");
                                                        }
                            */

                            /*
                                                        if (result.data.is_trial_expired === "1") {
                                                            $('#conveythis_trial_finished').css('display', 'block')
                                                        }
                             */


                            if (typeof (result.data.is_confirmed) !== "undefined" && result.data.is_confirmed !== null
                                && typeof (result.data.activ_to) !== "undefined" && result.data.activ_to !== null) {
                                if (result.data.is_confirmed != 1 && result.data.activ_to > 0) {
                                    activeTo = result.data.activ_to;
                                    if (Math.floor(Date.now() / 1000) < activeTo) {
                                        documentLocale = document.querySelector('html').getAttribute('lang');
                                        active_to_time = new Date(activeTo * 1000).toLocaleString(documentLocale, {timeZone: 'UTC'});
                                        $("#conveythis_confirmation_message_warning > span").text(active_to_time);
                                        $('#conveythis_confirmation_message_warning').show();
                                    } else {
                                        $("#conveythis_confirmation_message_danger > span > b").text(result.data.email);
                                        $('#conveythis_confirmation_message_danger').show();
                                    }
                                }
                            }

                            if (typeof (result.data.word_limit) !== "undefined" && result.data.word_limit) {
                                $('#conveythis_word_translation_exceeded_warning').show();
                            }

                            if (typeof (result.data.views_limit_exceeded) !== "undefined" && result.data.views_limit_exceeded) {
                                $('#conveythis_views_limit_exceeded_warning').show();
                            }

                            if (typeof (result.data.languages_limit_exceeded) !== "undefined" && result.data.languages_limit_exceeded) {
                                $('#conveythis_languages_limit_exceeded_warning').show();
                            }
                        }
                    }
                });
            }
        } catch (e) {
        }
    }

    $('button#main-tab, button#widget-style-tab').on('shown.bs.tab', function (e) {
        let widget = document.querySelector('.widget-preview');
        let widgetPreviewStyle = document.getElementById('widget-preview-style');
        let widgetPreviewGeneral = document.getElementById('widget-preview-general');

        if (widget && widgetPreviewStyle && widgetPreviewGeneral) {
            if (this.id == 'widget-style-tab') {
                widgetPreviewStyle.appendChild(widget);
                widgetPreviewGeneral.innerHTML = '';
            } else {
                widgetPreviewGeneral.appendChild(widget);
                widgetPreviewStyle.innerHTML = '';
            }
        }
    });

    setTimeout(function () {
        // validateApiKey()
        getUserPlan();
    }, 1000);

});
