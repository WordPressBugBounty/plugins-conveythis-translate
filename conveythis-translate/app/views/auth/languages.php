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

        <div class="m-auto my-4 text-center" style="max-width: 500px;width: 100%">

            <div>Take a few steps to set up the plugin</div>

            <div class="m-auto my-4 text-center">
                <p>Enter the email you used to register in the <a href="https://app.conveythis.com/dashboard/" class="api-key-setting" target="_blank">Conveythis dashboard</a></p>
                <div class="ui input w-100">
                    <input type="email" name="email" id="conveythis_email" class="conveythis-input-text text-truncate" value="" placeholder="Enter email" >
                </div>
            </div>

            <div class="m-auto my-4 text-center">
                <p>Enter API key <span role="button" title="Paste your API key from Conveythis dashboard">❔<a href="https://app.conveythis.com/dashboard/" class="api-key-setting" target="_blank"> Get API key</a></span></p>
                <div class="ui input w-100">
                    <input type="text" name="api_key" id="conveythis_api_key" class="conveythis-input-text text-truncate"
                            value="<?php echo esc_html($this->variables->api_key) ?>"
                            placeholder="pub_*********"
                </div>
                <label class="validation-label" style="float: left; margin-top: 5px;">Invalid Email or API Key. Please verify your credentials and try again.</label>
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

            <div class="my-4">
                <input type="submit" name="submit" id="submit" class="btn btn-primary btn-custom" value="Continue">
            </div>
        </div>
    </div>
</form>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script>
    let submitBlocked = true;

    const handleValidationResponse = (data, form) => {
        let target_languages = data.data.target_languages;
        const validationLabel = form.querySelector('.validation-label');
        const inputElementsApiKey = form.querySelector('input#conveythis_api_key');
        const inputElementsEmail = form.querySelector('input#conveythis_email');
        const dropdownElements = form.querySelectorAll('.lang-selection');


        if (data.data.check !== false) {
            validationLabel.style.display = 'none';
            inputElementsApiKey.classList.remove('validation-failed');
            inputElementsEmail.classList.remove('validation-failed');
            updateSettings(form, dropdownElements, target_languages);
        } else {
            validationLabel.style.display = 'block';
            inputElementsApiKey.classList.add('validation-failed');
            inputElementsEmail.classList.add('validation-failed');
        }
    };

    const updateSettings = (form, dropdownElements, target_languages) => {
        const apiKeyValue = form.elements['api_key'].value;

        $.ajax({
            url: 'options.php',
            method: 'POST',
            data: {
                'api_key': apiKeyValue,
                'from_js': true
            },
            success: (response) => {
                if (response !== "null") {
                    const data = JSON.parse(response);
                    if (data.source_language && target_languages) {
                        $('.dropdown-current-language').removeClass('validation-failed');
                        $('.dropdown-target-languages').removeClass('validation-failed');
                    }
                    $('.dropdown-current-language').dropdown('set selected', data.source_language);
                    $('.dropdown-target-languages').dropdown('set selected', target_languages);
                }

                $('#submit').val('Save Settings');
                dropdownElements.forEach(block => block.style.display = 'block');

                $('#submit').off('click').on('click', () => {
                    $('input[name="source_language"]').removeClass('first-submit');
                    $('input[name="target_languages"]').removeClass('first-submit');
                    submitBlocked = false;
                    form.submit();
                });
            },
            error: () => {
                console.log('Failed to update settings. Please try again.');
            }
        });
    };

    const validateApiKey = (apiKeyValue, emailValue, form) => {
        console.log("* validateApiKey()");
        let domain_name = window.location.hostname;
        console.log("* domain_name" + ' ' + domain_name);
        $.ajax({
            url: 'https://api.conveythis.com/admin/accounts/check/',
            method: 'POST',
            data: { 'pub_key': apiKeyValue,
                'email': emailValue,
                'domain': domain_name
            },
            success: (response) => {
                handleValidationResponse(response, form);
            },
            error: () => {
                console.log('Server error, please contact support.');
            }
        });
    };

    document.getElementById('login-form-settings').addEventListener('submit', (e) => {
        if (submitBlocked) {
            e.preventDefault();

            const form = e.target;
            const apiKeyInput = form.elements['api_key'];
            const apiKeyValue = apiKeyInput.value;

            const emailInput = form.elements['email'];
            const emailValue = emailInput.value;

            validateApiKey(apiKeyValue, emailValue, form);
        } else {
            submitBlocked = true;
        }
    });
</script>