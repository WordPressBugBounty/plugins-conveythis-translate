<form id="login-form-settings" method="POST" action="options.php">
    <?php
    settings_fields('my-plugin-settings');
    do_settings_sections('my-plugin-settings');
    ?>
    <div class="key-block mt-5">

        <div>
            <a href="https://www.conveythis.com/" target="_blank">
                <img src="<?php echo esc_url(CONVEY_PLUGIN_PATH)?>app/widget/images/conveythis-logo-vertical-blue.png" alt="ConveyThis">
            </a>
        </div>

        <div class="m-auto my-2 text-center" style="max-width: 500px;width: 100%">

<!--            <div>Take a few steps to set up the plugin</div>-->
<!--
            <div class="m-auto my-4 text-center">
                <p>Enter the email you used to register in the <a href="https://app.conveythis.com/dashboard/" class="api-key-setting" target="_blank">Conveythis dashboard</a></p>
                <div class="ui input w-100">
                    <input type="email" name="email" id="conveythis_email" class="conveythis-input-text text-truncate" value="" placeholder="Enter email" >
                </div>
            </div>
-->
            <div class="m-auto my-4 text-center">
                <p>
                    Enter your API key
                    <span>
                        &nbsp;|&nbsp;
                           </span>
                        <a href="<?php echo CONVEYTHIS_APP_URL . '/setup/?technology=wordpress&domain_name=' . parse_url(home_url(), PHP_URL_HOST)  ?>" class="api-key-setting" target="_blank">
                            Need an API key?
                        </a>

                </p>
                <div class="ui input w-100">
                    <input type="text" name="api_key" id="conveythis_api_key" class="conveythis-input-text text-truncate"
                            value="<?php echo esc_html($this->variables->api_key) ?>"
                            placeholder="pub_*********"
                </div>
            </div>
            <div class="validation-label">
                <div class="validation-title">
                    We couldn’t verify your API key.
                </div>
                <p class="validation-text">
                    Please check the key and try again.
                </p>
                <div class="validation-links">
                    <a href="<?php echo CONVEYTHIS_APP_URL . '/setup/?technology=wordpress&domain_name=' . parse_url(home_url(), PHP_URL_HOST)  ?>" class="api-key-setting" target="_blank">
                        Complete the setup
                    </a>
                    <span>&nbsp;&middot;&nbsp;</span>
                    <a href="https://support.conveythis.com" target="_blank" rel="noopener">
                        Contact support
                    </a>
                </div>
            </div>

            <div class="lang-selection my-4" style="display: none">
                <p>What is the source (current) language of your website?</p>
                <div class="ui dropdown fluid search selection  dropdown-current-language">
                    <input type="hidden" class="first-submit" name="source_language" value="<?php echo esc_html($this->variables->source_language); ?>">
                    <i class="dropdown icon"></i>
                    <div class="default text"><?php echo  esc_html(__( 'Select source language', 'conveythis-translate' )); ?></div>
                    <div class="menu">

                        <?php foreach( $this->variables->languages as $language ): ?>

                            <div class="item" data-value="<?php echo  esc_attr( $language['code2'] ); ?>">
                                <?php echo esc_html( $language['title_en'], 'conveythis-translate' ); ?>
                            </div>

                        <?php endforeach; ?>

                    </div>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-exclamation-circle" viewBox="0 0 16 16">
                        <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                        <path d="M7.002 11a1 1 0 1 1 2 0 1 1 0 0 1-2 0zM7.1 4.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 4.995z"/>
                    </svg>
                </div>
            </div>
            <div class="lang-selection my-4" style="display: none">
                <p>Choose languages you want to translate into.</p>
                <div class=" ui dropdown  fluid multiple search selection dropdown-target-languages">
                    <input type="hidden" name="target_languages" value="<?php echo esc_attr(implode( ',', $this->variables->target_languages )); ?>">
                    <i class="dropdown icon"></i>
                    <div class="default text">French, German, Italian, Portuguese…</div>
                    <div class="menu">

                        <?php foreach ($this->variables->languages as $language): ?>

                            <div class="item target-language-<?php echo esc_attr($language['code2']); ?>" data-value="<?php echo esc_attr($language['code2']); ?>">
                                <?php echo esc_html($language['title_en'], 'conveythis-translate'); ?>
                            </div>

                        <?php endforeach; ?>

                    </div>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-exclamation-circle" viewBox="0 0 16 16">
                        <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                        <path d="M7.002 11a1 1 0 1 1 2 0 1 1 0 0 1-2 0zM7.1 4.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 4.995z"/>
                    </svg>
                </div>
            </div>

            <div id="button_continue" class="my-4">
                <input type="submit" name="submit" id="submit" class="btn btn-primary btn-custom" value="Continue">
            </div>

            <div id="please_wait_message" class="my-4 btn btn-primary btn-custom d-none">
                <div class="please_wait">
                    <span class="please_wait_spinner me-2"></span>
                    <span>Please wait...</span>
                </div>

            </div>

        </div>
    </div>
    </form>

    <!-- Local styles for API key error card (works even if style.css is cached) -->
    <style>
        #login-form-settings .key-block .validation-label {
            display: none;
            margin: 20px auto 0 auto;
            padding: 12px 16px 12px 44px;
            border-radius: 5px;
            background: #fff7ec !important;
            border: 1px solid #ffd9b5 !important;
            color: #4a2c12 !important;
            font-size: 13px;
            line-height: 1.5;
            position: relative;
            text-align: left;
        }

        #login-form-settings .key-block .validation-label::before {
            content: "!";
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #ffb347;
            color: #fff;
            font-weight: 700;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #login-form-settings .key-block .validation-title {
            font-weight: 600;
            margin: 0 0 4px 0;
        }

        #login-form-settings .key-block .validation-text {
            margin: 0 0 6px 0;
        }

        #login-form-settings .key-block .validation-links {
            font-size: 12px;
            color: #8b6a3c;
        }

        #login-form-settings .key-block .validation-links a {
            font-weight: 500;
        }

        #login-form-settings .key-block .validation-links span {
            color: #d4b48a;
        }
    </style>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script>
    let submitBlocked = true; // flag to prevent initial form submit

    // errorContext: { noData: true } = request failed / no response, { apiError: true } = API returned error status
    const handleValidationResponse = (data, form, apiKeyValue, errorContext) => {
        const validationLabel = form.querySelector('.validation-label');
        const validationTitle = validationLabel ? validationLabel.querySelector('.validation-title') : null;
        const validationText = validationLabel ? validationLabel.querySelector('.validation-text') : null;
        const inputElementsApiKey = form.querySelector('input#conveythis_api_key');
        const dropdownElements = form.querySelectorAll('.lang-selection');

        const setupLink = form.querySelector('a.api-key-setting');
        const setupUrl = (setupLink && setupLink.href) ? setupLink.href : 'https://app.conveythis.com/setup/?technology=wordpress';
        const linkHtml = '<a href="' + setupUrl + '" target="_blank" rel="noopener" class="api-key-setting">here</a>';

        const isValid = data && data.data && data.data.check !== false;
        const hasKey = typeof apiKeyValue === 'string' && apiKeyValue.trim().length > 0;

        if (isValid) {
            let target_languages = data.data.target_languages;
            validationLabel.style.display = 'none';
            inputElementsApiKey.classList.remove('validation-failed');
            updateSettings(form, dropdownElements, target_languages);
            return;
        }

        validationLabel.style.display = 'block';
        inputElementsApiKey.classList.add('validation-failed');

        if (validationTitle) {
            validationTitle.textContent = 'We couldn’t verify your API key.';
        }

        if (validationText) {
            if (errorContext && errorContext.noData) {
                validationText.textContent = 'We couldn’t connect to the server. Please try again in a moment.';

            } else if (errorContext && errorContext.dataMissing) {
                validationText.textContent = 'Something went wrong while validating your API key. Please try again.';

            } else if (errorContext && errorContext.apiError) {
                validationText.textContent = 'Complete the setup for this project to get a valid API key. Copy it from the setup page.';

            } else if (!hasKey) {
                validationText.textContent = 'Enter your API key to continue. It becomes available after you complete the setup.';

            } else {
                const domain = window.location.hostname;
                validationText.textContent = 'Make sure you completed setup and copied the key from the correct setup page.';
            }
        }
    };

    const updateSettings = (form, dropdownElements, target_languages) => {
        const apiKeyValue = form.elements['api_key'].value; // get API key value

        $.ajax({
            url: 'options.php', // WP options endpoint
            method: 'POST',
            data: {
                'api_key': apiKeyValue, // send API key
                'from_js': true // flag request from JS
            },
            success: (response) => {
                if (response !== "null") { // if response exists
                    const data = JSON.parse(response); // parse JSON
                    if (data.source_language && target_languages) {
                        $('.dropdown-current-language').removeClass('validation-failed'); // clear source validation
                        $('.dropdown-target-languages').removeClass('validation-failed'); // clear target validation
                    }
                    $('.dropdown-current-language').dropdown('set selected', data.source_language); // set source language
                    $('.dropdown-target-languages').dropdown('set selected', target_languages); // set target languages
                }

                // $('#submit').val('Save Settings');
                // $('#submit').val('Please wait...');
                // $('#submit').prop('disabled', true);

                $('#button_continue').addClass('d-none'); // hide continue button
                $('#please_wait_message').removeClass('d-none'); // show please wait message
                // return

                // dropdownElements.forEach(block => block.style.display = 'block');

                //  $('#submit').off('click').on('click', () => {
                $('input[name="source_language"]').removeClass('first-submit'); // remove first submit flag
                $('input[name="target_languages"]').removeClass('first-submit'); // remove first submit flag
                submitBlocked = false; // allow next submit
                //  form.submit();
                //  });

                setTimeout(() => {
                    const submitBtn = document.getElementById('submit'); // get submit button
                    if (submitBtn) {
                        submitBtn.click(); // trigger final submit
                    }
                    else{
                    }
                }, 100); // small delay before submit

            },
            error: () => {
            }
        });
    };

    //  const validateApiKey = (apiKeyValue, emailValue, form) => {
    const validateApiKey = (apiKeyValue, form) => {
        let domain_name = window.location.hostname; // get current domain
        let url = <?php echo json_encode(CONVEYTHIS_API_URL); ?> + '/admin/accounts/check_wordpress/'; // API validation endpoint
        $.ajax({
            // url: 'https://api.conveythis.com/admin/accounts/check/',
            url: url,
            method: 'POST',
            data: {
                'pub_key': apiKeyValue, // send public API key
                //'email': emailValue,
                'domain': domain_name // send domain
            },
            success: (response) => {
                if (response.status === "error") {
                    handleValidationResponse({ data: { check: false } }, form, apiKeyValue, { apiError: true });
                } else if (!response || !response.data) {
                    handleValidationResponse({ data: { check: false } }, form, apiKeyValue, { dataMissing: true });
                } else {
                    handleValidationResponse(response, form, apiKeyValue);
                }
            },
            error: (xhr, status, error) => {
                handleValidationResponse({ data: { check: false } }, form, apiKeyValue, { noData: true });
            }
        });
    };

    const settingsForm = document.getElementById('login-form-settings');

    if (settingsForm) {
        settingsForm.addEventListener('submit', (e) => {
            if (submitBlocked) { // first submit blocked
                e.preventDefault(); // stop default submit

                const form = e.target; // current form
                const apiKeyInput = form.elements['api_key']; // API key input
                const apiKeyValue = apiKeyInput.value; // API key value

                //  const emailInput = form.elements['email'];
                //  const emailValue = emailInput.value;

                //  validateApiKey(apiKeyValue, emailValue, form);
                validateApiKey(apiKeyValue, form); // validate before real submit
            } else {
                submitBlocked = true; // reset block for next time
            }
        });
    }
</script>