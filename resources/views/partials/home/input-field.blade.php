


<div class="input-container admin-only editor-only" id="input-container">

    <div class="isTypingStatus"></div>

    <div class="input-controls" id="input-controls">
        @if(!$lite)
        <button class="btn-xs expand-btn" onclick="toggleRelativePanelClass('input-controls', this,'expanded')">
            <div class="icon">
                <x-icon name="chevron-up"/>
            </div>
        </button>
        @endif

        <div class="minimized-content">
            <div class="left">

                @if($activeModule === 'chat')
                    <button class="btn-xs fast-access-btn" onclick="startNewChat()">
                        <x-icon name="new"/>
                        <div class="tooltip">
                            {{ $translation["StartNewChat"] }}
                        </div>
                    </button>
                @endif

                @if(!$lite && $activeModule === 'chat')
                    <button class="btn-xs fast-access-btn" value="system_prompt_panel" onclick="toggleRelativePanelClass('input-controls', this,'expanded'); switchControllerProp(this, 'system_prompt_panel')">
                        <x-icon name="sliders"/>
                        <div class="tooltip">
                            {{ $translation["SystemPrompt"] }}
                        </div>
                    </button>

                @endif

                @if(!$lite)
                    <button class="btn-xs fast-access-btn" value="export-panel" onclick="toggleRelativePanelClass('input-controls', this,'expanded'); switchControllerProp(this, 'export-panel')">
                        <x-icon name="download"/>
                        <div class="tooltip">
                            {{ $translation["Export"] }}
                        </div>
                    </button>
                @endif

                @if(!$lite)
                    <button class="btn-xs fast-access-btn" style="padding:0px;" value="export-panel" onclick="toggleModelsExplained(true)">
                        <div style="font-size: 1.3rem; padding:0px 5px;">?</div>
                        <div class="tooltip">
                            {{ $translation["ModelsExplained"] }}
                        </div>
                    </button>
                @endif                
                
            </div>

            <div class="right">
                <div id="model-selectors">

                    <div class="burger-dropdown anchor-top-right" id="model-selector-burger">
                        @include('partials.home.components.models-list')
                    </div>
                
                    <div class="burger-btn-arrow burger-btn" onclick="openBurgerMenu('model-selector-burger', this, false, true, true)">
                        <div class="icon">
                            <x-icon name="chevron-up"/>
                        </div>
                        <div class="label model-selector-label"></div>
                    </div>
              
                </div>
            </div>
        </div>

        @if(!$lite)
        <div class="expanded-content">

            <div class="expanded-left">
                <div class="controls-container scroll-container">

                    <div class="control-buttons scroll-panel">
                        @if($activeModule === 'chat')

                        <button class="btn-xs menu-item" value="" onclick="switchControllerProp(this); startNewChat(); toggleRelativePanelClass('input-controls', this,'expanded');">
                            <x-icon name="new"/>
                            <div class="label">{{ $translation["StartNewChat"] }}</div>
                        </button>
                        @endif
                        
                        <button class="btn-xs menu-item" value="export-panel" style="padding-left:10px;" onclick="toggleModelsExplained(true)">
                            <div style="font-size: 1.3rem; padding: 0px 10px">?</div>
                            <div class="label">{{ $translation["ModelsExplained"] }}</div>
                        </button>

                        <button class="btn-xs menu-item" value="models_panel" onclick="switchControllerProp(this, 'models_panel')">
                            <x-icon name="layers"/>
                            <div class="label">{{ $translation["Models"] }}</div>
                        </button>
                        
                        @if($activeModule === 'chat')
                        <button class="btn-xs menu-item" value="system_prompt_panel" onclick="switchControllerProp(this, 'system_prompt_panel')">
                            <x-icon name="sliders"/>
                            <div class="label">{{ $translation["SystemPrompt"] }}</div>
                        </button>
                        @endif
                        
                        <button class="btn-xs menu-item" value="export-panel" onclick="switchControllerProp(this, 'export-panel')">
                            <x-icon name="download"/>
                            <div class="label">{{ $translation["Export"] }}</div>
                        </button>            
                    </div>

                </div>
            </div>
            <div class="expanded-right">
                <div class="controls-props scroll-container">
                    
                    <div class="scroll-panel" id="input-controls-props-panel">
                        
                        <div id="system_prompt_panel" class="prop-content">
                            <div contenteditable class="system_prompt_field" id="system_prompt_field"></div>
                        </div>

                        <div id="models_panel" class="prop-content">
                            @include('partials.home.components.models-list')
                        </div>

                        <div id="export-panel" class="prop-content">
                            
                            <button class="burger-item" id="export-btn-print" onclick="exportPrintPage()">
                                <div class="icon"></div>
                                <div class="label">{{ $translation["Print"] }}</div>
                            </button>

                            <button class="burger-item" id="export-btn-pdf" onclick="exportAsPDF()">
                                <div class="loading loading-sm">
                                    <x-icon name="loading"/>
                                </div>
                                <div class="icon"></div>
                                <div class="label">PDF {{ $translation["Download"] }}</div>
                            </button>

                            <button class="burger-item" id="export-btn-word" onclick="exportAsWord()">
                                <div class="loading loading-sm">
                                    <x-icon name="loading"/>
                                </div>
                                <div class="icon"></div>
                                <div class="label">Word {{ $translation["Download"] }}</div>
                            </button>

                            <button class="burger-item" id="export-btn-csv" onclick="exportAsCsv()">
                                <div class="loading loading-sm">
                                    <x-icon name="loading"/>
                                </div>
                                <div class="icon"></div>
                                <div class="label">CSV {{ $translation["Download"] }}</div>
                            </button>

                            <button class="burger-item" id="export-btn-json" onclick="exportAsJson()">
                                <div class="icon"></div>
                                <div class="label">JSON {{ $translation["Download"] }}</div>
                            </button>
                        </div>

                    </div>
                    
                </div>

            </div>
        </div>
        @endif

    </div>
    <div class="input" id="0">
       <div class="input-wrapper">
            <div id="drop-error-overlay" class="drop-error-overlay" style="display: none;">This model does not support document input!</div>
            
            <div id="drop-file-list" class="drop-file-list" style="display: none;"></div>

            <textarea  
                class="input-field"
                id="main-input-field" 
                type="text"
                data-files='[]'
                data-file-drop-enabled-placeholder="{{ $translation['Input_Placeholder_Chat_With_Filedrop'] }}"
                data-file-drop-disabled-placeholder="{{ $translation['Input_Placeholder_Chat'] }}"
                data-file-drop-error="{{ $translation['Filedrop_Error'] }}"
                data-file-drop-unsupported="{{ $translation['Filedrop_Unsupported'] }}"

                @if($activeModule === 'chat')
                    placeholder="{{ $translation['Input_Placeholder_Chat'] }}" 
                    oninput="resizeInputField(this);" 
                    onkeypress="onHandleKeydownConv(event)"
                @elseif($activeModule === 'groupchat')
                    placeholder="{{ $translation['Input_Placeholder_Room'] ." ". config('app.aiHandle')}}" 
                    oninput="resizeInputField(this); onGroupchatType()" 
                    onkeypress="onHandleKeydownRoom(event)"
                @endif

                onfocus="onInputFieldFocus(this); toggleOffRelativeInputControl(this)"
                onfocusout="onInputFieldFocusOut(this)"
                ondrop="handleDrop(this, event);"></textarea>

        </div>

        <div class="input-send tooltip-parent">
            @if($activeModule === 'chat')
                <div id="send-btn" onClick="onSendClickConv(this)">
            @elseif($activeModule === 'groupchat')
                <div id="send-btn" onClick="onSendClickRoom(this)">
            @endif
                    <div id="send-icon" class="send-btn-icon" >
                        <x-icon name="arrow-up"/>
                    </div>
                    <div id="stop-icon" class="send-btn-icon" style="display:none">
                        <x-icon name="stop"/>
                    </div>
                    <div id="loading-icon" class="send-btn-icon loading loading-lg" style="display:none">
                        <div class="loading">
                            <x-icon name="loading"/>
                        </div>
                    </div>
            </div>

            <div class="label tooltip tt-abs-up">
                {{ $translation["Send"] }}
            </div>

        </div>


        <div class="prompt-improvement-btn tooltip-parent" onclick="requestPromptImprovement(this)">
            <x-icon name="vector"/>
            <div class="label tooltip tt-abs-up">
                {{ $translation["PromptImprovement"] }}
            </div>
        </div>
    </div>
    <div class="model-capabilities">
        <span class="model-capability model-capability-image-gen" title="{{ $translation['Model_Capability_Image_Gen'] }}">&#x1F3DE;</span>
        <span class="model-capability model-capability-attachments" title="{{ $translation['Model_Capability_Attachments'] }}">&#x1F4CE;</span>
        <span class="model-capability model-capability-websearch" title="{{ $translation['Model_Capability_Websearch'] }}">&#x1F310;</span>
        @if($imageQuota['quota'])
            <span class="image-quota-info hint @if($imageQuota['reached']) warn_hint @endif">
                {!! $translation['Monthly image quota'] !!} - <span class="quota-value">{{$imageQuota['quota']}}</span>, {!! $translation['Monthly image quota remaining'] !!} - <span class="quota-counter">{{$imageQuota['remaining']}}</span>
            </span>
        @endif            
    </div>
</div>
