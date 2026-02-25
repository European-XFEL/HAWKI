<div class="modal"  id="models-explained">
	<div class="modal-panel">
        <div class="modal-content-wrapper">
            <div class="modal-content">
                <h1 class="center-text">{{ $translation["ModelsExplained"] }}</h1>
                In RAY you can switch between different models depending if you need fast answer, general chat, code generation or advanced analysis.<br> 
                Different tools available depending on selected model:<br> 
                - image generation - if enabled just ask for it, <br>
                - file upload - if enabled drag and drop your files on the input area,<br>
                - websearch - if enabled it will be used automatically i.e searching current weather.<br><br>
                <div class="center-text"><img src="{{url('media/help_model_select.png')}}" width="755"></div>
                <h1 class="center-text">Current model list</h1>
                <ul>
                    @foreach(config('model_providers.providers') as $provider)
                        @foreach($provider['models'] as $model)
                            @if($model['visible'])
                                @if($model['separator']) <hr> @endif
                                <li>
                                    <b>{{$model['label']}}</b>
                                    @if($model['description']) - {!! $model['description'] !!} @endif
                                    @if($model['enable_image_generation'] || $model['enable_document_input'] || $model['enable_web_search']) 
                                        @php 
                                            $_tools = [];
                                            if($model['enable_image_generation']) $_tools[] = 'image generation';
                                            if($model['enable_document_input']) $_tools[] = 'document input';
                                            if($model['enable_web_search']) $_tools[] = 'web search';
                                        @endphp
                                        <div class="gray-text">Tools: {{implode(', ', $_tools)}}</div>
                                    @endif
                                </li>
                            @endif
                        @endforeach
                    @endforeach
                </ul>
                <br>
            </div>
            <div class="closeButton" onclick="toggleModelsExplained(false)">
                <svg viewBox="0 0 100 100"><path class="fill-svg" d="M 19.52 19.52 a 6.4 6.4 90 0 1 9.0496 0 L 51.2 42.1504 L 73.8304 19.52 a 6.4 6.4 90 0 1 9.0496 9.0496 L 60.2496 51.2 L 82.88 73.8304 a 6.4 6.4 90 0 1 -9.0496 9.0496 L 51.2 60.2496 L 28.5696 82.88 a 6.4 6.4 90 0 1 -9.0496 -9.0496 L 42.1504 51.2 L 19.52 28.5696 a 6.4 6.4 90 0 1 0 -9.0496 z"/></svg>
            </div>
        </div>
	</div>
</div>
