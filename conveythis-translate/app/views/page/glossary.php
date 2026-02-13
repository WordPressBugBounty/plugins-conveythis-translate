<div class="tab-pane fade" id="v-pills-glossary" role="tabpanel" aria-labelledby="glossary-tab">
   <div class="form-group paid-function">
       <div class="title">Glossary</div>

               <div class="glossary-description">
                   <p>To keep the consistency of your translations, tell ConveyThis which keyword or phrase should be translated in a certain way or not translated at all.</p>
                   <p>For example, when we translate the ConveyThis website, we specify the brand name <strong>ConveyThis</strong> to stay as <strong>ConveyThis</strong> in all languages.</p>
                   <p><strong>Glossary is case-sensitive.</strong> For example, <code>ConveyThis</code> and <code>CONVEYTHIS</code> are treated as different entries.</p>
                   <p><strong>Note:</strong> If you have a caching plugin installed, the data may be out of date. Please clear the cache for pages that use your glossary rules.</p>
               </div>

                <div class="glossary-filter mb-2">
                    <div class="mb-2">
                        <label for="glossary_search" class="me-2">Search:</label>
                        <input type="text" id="glossary_search" class="form-control conveythis-input-text" placeholder="Search by word or translation..." style="max-width: 280px; display: inline-block;">
                    </div>
                    <div>
                        <label for="glossary_filter_language" class="me-2">Filter by language:</label>
                        <select id="glossary_filter_language" class="form-control" style="max-width: 200px; display: inline-block;">
                            <option value="">Show all</option>
                            <option value="__all__">All languages</option>
                            <?php if (isset($this->variables->languages) && isset($this->variables->target_languages)) : ?>
                                <?php foreach ($this->variables->languages as $language) : ?>
                                    <?php if (in_array($language['code2'], $this->variables->target_languages)) : ?>
                                        <option value="<?php echo esc_attr($language['code2']); ?>"><?php echo esc_html($language['title_en']); ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                <div id="glossary_wrapper">
                    <?php $languages = array_combine(array_column($this->variables->languages, 'code2'), array_column($this->variables->languages, 'title_en')); ?>
                    <?php if (
                            isset($this->variables->glossary) &&
                            is_array($this->variables->system_links) &&
                            count($this->variables->glossary) > 0
                    ) : ?>
                        <?php foreach( $this->variables->glossary as $glossary ): ?>
                            <?php if (is_array($glossary)) : ?>
                                <div class="glossary position-relative w-100" data-target-language="<?php echo esc_attr(isset($glossary['target_language']) ? $glossary['target_language'] : ''); ?>">
                                    <input type="hidden" class="glossary_id" value="<?php echo (isset($glossary['glossary_id']) ? esc_attr($glossary['glossary_id']) : '') ?>"/>
                                    <a role="button" class="conveythis-delete-page glossary-delete-btn" data-action="delete-glossary-row" aria-label="Delete rule"></a>
                                    <div class="row w-100 mb-2">
                                        <div class="col-md-3">
                                            <div class="ui input">

                                                <input
                                                        type="text"
                                                        class="source_text w-100 conveythis-input-text"
                                                        placeholder="Enter Word"
                                                        value="<?php echo (isset($glossary['source_text']) ? esc_attr($glossary['source_text']): '') ?>"
                                                >

                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <select class="form-control rule w-100" required>
                                                <option value="prevent" <?php echo ($glossary['rule'] == 'prevent') ? 'selected': '' ?> >Don't translate</option>
                                                <option value="replace" <?php echo ($glossary['rule'] == 'replace') ? 'selected': '' ?> >Translate as</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="ui input">
                                                <input type="text" class="conveythis-input-text translate_text w-100" value="<?php echo (isset($glossary['translate_text']) ? esc_attr($glossary['translate_text']): '') ?>" <?php echo (isset($glossary['rule']) &&  $glossary['rule'] == 'prevent' ? ' disabled="disabled"' : '');?>>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <select class="form-control target_language w-100">
                                                <option value="">All languages</option>
                                                <?php foreach ($this->variables->languages as $language) :?>
                                                    <?php if (in_array($language['code2'], $this->variables->target_languages)):?>
                                                        <option value="<?php echo  esc_attr($language['code2']); ?>"<?php echo ($glossary['target_language'] == $language['code2']?' selected':'')?>>
                                                            <?php echo  esc_html($languages[$language['code2']]); ?>
                                                        </option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div id="glossary_pagination" class="glossary-pagination mt-2 mb-2" style="display: none;">
                    <button type="button" id="glossary_prev_page" class="btn btn-sm btn-outline-secondary">Previous</button>
                    <span id="glossary_page_info" class="mx-2 align-middle">Page 1 of 1</span>
                    <button type="button" id="glossary_next_page" class="btn btn-sm btn-outline-secondary">Next</button>
                </div>
                <input type="hidden" id="glossary_data" name="glossary" value='<?php echo json_encode( $this->variables->glossary ); ?>'>
                <input type="file" id="glossary_import_file" accept=".csv,.json,text/csv,application/json" style="display: none;">
                <div class="glossary-actions glossary-buttons mt-2">
                    <button class="btn btn-sm btn-primary" type="button" id="add_glossary">Add more rules</button>
                    <button class="btn-default btn-sm glossary-btn fw-bold ms-2" type="button" id="glossary_export">Export CSV</button>
                    <button class="btn-default btn-sm glossary-btn fw-bold ms-2" type="button" id="glossary_import">Import CSV</button>
                </div>
       <label class="hide-paid" for="">This feature is not available on Free plan. If you want to use this feature, please <a href="https://app.conveythis.com/dashboard/pricing/?utm_source=widget&utm_medium=wordpress" target="_blank" class="grey">upgrade your plan</a>.</label>
   </div>
</div>