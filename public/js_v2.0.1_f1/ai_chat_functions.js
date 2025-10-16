
let convMessageTemplate;
let chatItemTemplate;
let activeConv;
let defaultPromt;
let chatlogElement;

function initializeAiChatModule(chatsObject){

    convMessageTemplate = document.getElementById('message-template');
    chatItemTemplate = document.getElementById('selection-item-template');
    chatlogElement = document.querySelector('.chatlog');

    defaultPromt = translation.Default_Prompt;

    const systemPromptFields = document.querySelectorAll('.system_prompt_field');
    systemPromptFields.forEach(field => {
        field.textContent = defaultPromt;
    });

    chats = chatsObject.original;

    chats.forEach(conv => {
        createChatItem(conv);
    });

    if(document.querySelector('.trunk').childElementCount == 0){
        chatlogElement.classList.add('start-state');
    }

    initializeChatlogFunctions();

}


function onHandleKeydownConv(event){

    if(getSendBtnStat() === SendBtnStatus.SENDABLE){
        if(event.key == "Enter" && !event.shiftKey){
            event.preventDefault();
            selectActiveThread(event.target);
            sendMessageConv(event.target);
        }
    }
}

function handleDrop(textArea, event) {
    
    event.preventDefault();
    extensionErrors = [];
    unsupported = true;
    if (activeModel && activeModel.enable_document_input) {
        const files = event.dataTransfer.files;
        let fileArray = textArea.dataset.files ? JSON.parse(textArea.getAttribute('data-files')) : [];
        
        for (let file of files) {
            if (['application/pdf', 'image/png', 'image/jpeg'].includes(file.type) && file.size / 1000 <= activeModel.max_attachment_size_kb) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    const base64Content = event.target.result;
                    fileArray.push({ name: file.name, size: file.size, content: base64Content, type: file.type});
                    textArea.setAttribute('data-files', JSON.stringify(fileArray));
                    displayFiles(fileArray, textArea);
                };
                reader.readAsDataURL(file);
            } else {
                extensionErrors.push({ name: file.name, type: file.type, size: file.size });
            }
        }
        
        textArea.setAttribute('data-files', JSON.stringify(fileArray));
        unsupported = false;
    }
    if (extensionErrors.length > 0 || unsupported) {
        // Show the non-visible overlay and then fade it out
        const overlay = document.getElementById('drop-error-overlay');
        overlay.style.position = 'absolute'; 
        overlay.style.top = '0'; 
        overlay.style.left = '0'; 
        overlay.style.display = 'block';
        if (unsupported) {
            overlay.innerText = textArea.getAttribute("data-file-drop-unsupported");
        } else {
            var errorMsg = textArea.getAttribute("data-file-drop-error").replace("${maxFileSize}", activeModel.max_attachment_size_kb) + "<br/>";
            for (let fileError of extensionErrors) {
                errorMsg += `${fileError.name}: ${fileError.type} (${Math.floor(fileError.size / 1000)} kB)<br/>`;
            }
            
            overlay.innerHTML = errorMsg;
        }
        setTimeout(() => {
            overlay.style.transition = 'opacity 7s';
            overlay.style.opacity = 0;
            setTimeout(() => {
                overlay.style.display = 'none';
                overlay.style.opacity = 1; 
            }, 7000);
        }, 100);
    }
}

function displayFiles(files, textArea) {
    const fileListDiv = textArea.parentElement.querySelector('#drop-file-list');
    if (!fileListDiv) return;
    fileListDiv.innerHTML = '';
    if (files.length > 0) {
        fileListDiv.style.display = 'block';
        files.forEach((file, index) => {
            const fileItem = document.createElement('div');
            fileItem.classList.add('drop-file-item');
            const removeButton = document.createElement('span');
            removeButton.classList.add('remove-file');
            removeButton.innerHTML = '&#10006;';
            removeButton.onclick = function() {
                removeFile(index, textArea);
            };


            fileItem.innerHTML = file.name + ` (${(file.size / 1024).toFixed(0)} KB)`;
            fileItem.appendChild(removeButton);
            fileListDiv.appendChild(fileItem);

        });
    } else {
        fileListDiv.style.display = 'none';
    }
}


function clearFiles(inputField) {
   
    const fileListDiv = inputField.parentElement.querySelector('#drop-file-list');
    if (!fileListDiv) return;
    fileListDiv.innerHTML = '';
    fileListDiv.style.display = 'none';
    
    // Assuming there's a dataset object to clear files from
    if (inputField.dataset && inputField.dataset.files) {
        inputField.dataset.files = []; // Clear dataset.files
    }
    
}


function removeFile(index, textArea) {
    let fileArray = JSON.parse(textArea.getAttribute('data-files'));
    fileArray.splice(index, 1); // Remove the file at the specified index
    textArea.setAttribute('data-files', JSON.stringify(fileArray));
    displayFiles(fileArray, textArea);
}

function onSendClickConv(btn){

    if(getSendBtnStat() === SendBtnStatus.SENDABLE){

        selectActiveThread(btn);
        //get inputfield relative to the button for multiple inputfields
        const input = btn.closest('.input');
        const inputField = input.querySelector('.input-field');
        sendMessageConv(inputField);
    }
    else if(getSendBtnStat() === SendBtnStatus.STOPPABLE){
        abortCtrl.abort();
    }
}

// SEND MESSAGE FUNCTION
async function sendMessageConv(inputField) {
    // block empty input field.
    if (inputField.value.trim() == "") {
        return;
    }
    inputText = String(escapeHTML(inputField.value.trim()));

    setSendBtnStatus(SendBtnStatus.LOADING);

    // format auxiliary data that me might want to add
    let files = [];
    try {
        files = JSON.parse(inputField.dataset.files);
    } catch (error) {}
    auxiliaries = [];
    for(let file of files) {
        auxiliaries.push({
            type: "attachment:" + file.type,
            content: JSON.stringify({
                content: file.content,
                name: file.name,
                type: file.type,
                size: file.size
            })
        })
    }

    // we have the files, clear them from display
    clearFiles(inputField);

    //create a message object.
    let messageObj = {
        message_role: 'user',
        content: inputText,
        filteredContent: detectMentioning(inputText),
        author: {
            username: userInfo.username,
            name: userInfo.name,
            avatar_url: userInfo.avatar_url,
        },
        auxiliaries: auxiliaries,
    };

    // empty input field
    inputField.value = "";
    resizeInputField(inputField);

    // if the chat is empty we need to initialize a new chatlog.
    let initConvPromise;
    if (document.querySelector('.trunk').childElementCount === 0) {
        await initNewConv(messageObj);
    }
    else{
        // ADDING MESSAGE TO CHATLOG
        // encrypt message
        const convKey = await keychainGet('aiConvKey');
        const cryptoMsg = await encryptWithSymKey(convKey, messageObj.content, false);
        
        messageObj.ciphertext = cryptoMsg.ciphertext;
        messageObj.iv = cryptoMsg.iv;
        messageObj.tag = cryptoMsg.tag;

        // handle auxiliaries data
        messageObj.auxiliaries = messageObj.auxiliaries ?? []
        auxiliaries = []
        for (const auxiliary of messageObj.auxiliaries) {
            const cryptoMsg = await encryptWithSymKey(convKey, auxiliary.content, false);
            auxiliaries.push({
                content: cryptoMsg.ciphertext,
                iv: cryptoMsg.iv,
                tag: cryptoMsg.tag,
                type: auxiliary.type,
            })
        };

        // Submit Message to server.
        const requestObj = {
            'isAi': false,
            'threadID': activeThreadIndex,
            'content': messageObj.ciphertext,
            'iv': messageObj.iv,
            'tag': messageObj.tag,
            'completion': true,
            'auxiliaries': auxiliaries
        }
        const submittedObj = await submitMessageToServer(requestObj, `/req/conv/sendMessage/${activeConv.slug}`);
        submittedObj.content = messageObj.content;
        submittedObj.username = userInfo.username;
        // these need to be the unencrypted auxiliaries
        submittedObj.auxiliaries = messageObj.auxiliaries;
        
        // create and add message element to chatlog.
        const messageElement = addMessageToChatlog(submittedObj);
        messageElement.dataset.rawMsg = submittedObj.content;
        messageElement.dataset.auxiliaries = JSON.stringify(messageObj.auxiliaries);
        scrollToLast(true, messageElement);
    }

    const msgAttributes = {
        'threadIndex': activeThreadIndex,
        'broadcasting': false,
        'slug': '',
        'stream': true,
        'model': activeModel.id,
    }

    buildRequestObjectForAiConv(msgAttributes);
}


async function buildRequestObjectForAiConv(msgAttributes, messageElement = null, isUpdate = false, isDone = null){
    // let messageElement;
    let msg = "";
    let messageObj;
    let metadata;

    // Start buildRequestObject processing
    buildRequestObject(msgAttributes, async (data, done) => {
        if(data){
            if(!msgAttributes['broadcasting'] && msgAttributes['stream']){
                setSendBtnStatus(SendBtnStatus.STOPPABLE);
            }
            
            const {messageText, groundingMetadata} = deconstContent(data.content);
            if(groundingMetadata != ""){
                metadata = groundingMetadata;
            }

            const content = messageText;
            
            if (!data.isFinalText) {
                msg += content;
            } else {
                // final update from OpenAI model
                msg = content;
            }
            messageObj = data;
            messageObj.message_role = 'assistant';
            messageObj.content = content;
            messageObj.completion = data.isDone;
            messageObj.model = msgAttributes['model'];
            messageObj.auxiliaries = data.auxiliaries;

            if (!messageElement) {
                initializeMessageFormating()
                messageElement = addMessageToChatlog(messageObj, false);
            } else {
                var annotations = [];
                for (aux of messageObj.auxiliaries ?? []) {
                    if (aux['type'] == 'webSearchAnnotations') {
                        // content in this case is a JSON string, if this was passed
                        const content = JSON.parse(aux['content']);
                        annotations = annotations.concat(content);
                    }
                }
                displayAnnotations(messageElement, annotations);
            }
            messageElement.dataset.rawMsg = msg;

            // need to merge image auxiliaries - we preserve the last update
            var auxiliaries =  messageElement.dataset.auxiliaries ? JSON.parse( messageElement.dataset.auxiliaries) : [];
            auxiliaries.reverse();
            for (const aux of auxiliaries) {
            
                if (aux['type'] == 'imageResponse') {
                    messageObj.auxiliaries.push(aux);
                    break;
                }
            }

            messageElement.dataset.auxiliaries = JSON.stringify(messageObj.auxiliaries);
    
            const msgTxtElement = messageElement.querySelector(".message-text");

            msgTxtElement.innerHTML = formatChunk(content, groundingMetadata, data.isFinalText);
            formatMathFormulas(msgTxtElement);
            formatHljs(messageElement);

            if (groundingMetadata &&
                groundingMetadata != '' &&
                groundingMetadata.searchEntryPoint &&
                groundingMetadata.searchEntryPoint.renderedContent) {

                addGoogleRenderedContent(messageElement, groundingMetadata);
            }
            else{
                if(messageElement.querySelector('.google-search')){
                    messageElement.querySelector('.google-search').remove();
                }
            }


            if(messageElement.querySelector('.think')){
                scrollPanelToLast(messageElement.querySelector('.think').querySelector('.content-container'));
            }

            // add any images we might have
            for (aux of messageObj.auxiliaries) {
                
                if (aux['type'] == 'imageResponse') {
                    const img = document.createElement('img');
                    const imageData = aux['content'];
                    img.src = imageData.startsWith('data:') ? imageData : 'data:image/png;base64,' + imageData;
                    img.alt = 'image';
                    img.width = '500';
                    msgTxtElement.appendChild(img);

                    // make this friendly for the clipboard
                    messageElement.dataset.imageData = imageData;
                }  else if (aux['type'] == 'thinkingUpdates') {
                    // content in this case is a JSON string, if this was passed
                    
                    const updates = JSON.parse(aux['content']);
                    
                    $('.preparing_completion_status').empty(); // Clear existing content

                    const displayUpdates = updates.slice(0, 3).map(update => 
                        update.length > 20 ? update.substring(0, 20) + '...' : update
                    );

                    if (updates.length > 3) {
                        displayUpdates.push(`+${updates.length - 3}`);
                    }

                    $('.preparing_completion_status').append(`<span>${displayUpdates.join(', ')}</span>`);

                }
            }

            
            scrollToLast(false, messageElement);
        }

        if(done){
            setSendBtnStatus(SendBtnStatus.SENDABLE);

            const cryptoContent = JSON.stringify({
                text: msg,
                groundingMetadata : metadata
            });

            const convKey = await keychainGet('aiConvKey');
            const cryptoMsg = await encryptWithSymKey(convKey, cryptoContent, false);

            messageObj.ciphertext = cryptoMsg.ciphertext;
            messageObj.iv = cryptoMsg.iv;
            messageObj.tag = cryptoMsg.tag;

            // handle auxiliaries data
            messageObj.auxiliaries = messageObj.auxiliaries ?? []
            auxiliaries = []
            for (const auxiliary of messageObj.auxiliaries) {
                const cryptoMsg = await encryptWithSymKey(convKey, auxiliary.content, false);
                aux = {
                    content: cryptoMsg.ciphertext,
                    iv: cryptoMsg.iv,
                    tag: cryptoMsg.tag,
                    type: auxiliary.type,
                    
                };
                if (isUpdate) {
                    aux.id = auxiliary.id;
                }
                auxiliaries.push(aux);
            };
            activateMessageControls(messageElement);

            const requestObj = {
                'threadID': activeThreadIndex,
                'content': messageObj.ciphertext,
                'iv': messageObj.iv,
                'tag': messageObj.tag,
                'model': messageObj.model,
                'completion': messageObj.completion,
                'auxiliaries': auxiliaries
            }

            if(isUpdate){
                requestObj.message_id = messageElement.id;
                await requestMsgUpdate(requestObj, messageElement, `/req/conv/updateMessage/${activeConv.slug}`)
            }
            else{
                requestObj.isAi = true;
                //console.log('checkpoint')
                const submittedObj = await submitMessageToServer(requestObj, `/req/conv/sendMessage/${activeConv.slug}`);

                submittedObj.content = cryptoContent;
                messageElement.dataset.rawMsg = msg;
                messageElement.dataset.auxiliaries = JSON.stringify(messageObj.auxiliaries);
                // messageElement.dataset.groundingMetadata = metadata;
                addGoogleRenderedContent(messageElement, metadata);
                updateMessageElement(messageElement, submittedObj);
                activateMessageControls(messageElement);
            }

            if(isDone){
                isDone(true);
            }
        }
    });
}


//#region CONVERSATION FUNCTIONS

/// Initializing a new conversation.
async function initNewConv(messageObj){

    // if start State panel is there remove it.
    chatlogElement.classList.remove('start-state');

    // empty chatlog
    clearChatlog();
    // 
    history.replaceState(null, '', `/chat`);

    //add new message Element.
    const messageElement = addMessageToChatlog(messageObj, false);

    //create conversation button in the list.
    const convItem = createChatItem();
    convItem.classList.add('active');

    //create conversation name.
    const convName = await generateChatName(messageObj.content, convItem);
    // console.log(convName);
    //submit conv to server.
    // after the server has accepted Submission conv data will be updated.
    const convData = await submitConvToServer(convName);

    //assign Slug to conv Item.
    convItem.setAttribute('slug', convData.slug);
    //update URL
    history.replaceState(null, '', `/chat/${convData.slug}`);

    //update active conv cache.
    activeConv = convData;

    //Encyrpt message
    const convKey = await keychainGet('aiConvKey');
    const contData = await encryptWithSymKey(convKey, messageObj.content);
   
    messageObj.ciphertext = contData.ciphertext;
    messageObj.iv = contData.iv;
    messageObj.tag = contData.tag;

    messageObj.auxiliaries = messageObj.auxiliaries ?? []
    auxiliaries = []
    for (const auxiliary of messageObj.auxiliaries) {
        const cryptoMsg = await encryptWithSymKey(convKey, auxiliary.content, false);
        auxiliaries.push({
            content: cryptoMsg.ciphertext,
            iv: cryptoMsg.iv,
            tag: cryptoMsg.tag,
            type: auxiliary.type,
        })
    };

    //submit message to server
    const requestObj = {
        'isAi': false,
        'threadID': activeThreadIndex,
        'content': messageObj.ciphertext,
        'iv': messageObj.iv,
        'tag': messageObj.tag,
        'completion': true,
        'auxiliaries': auxiliaries,
    }
    const submittedObj = await submitMessageToServer(requestObj, `/req/conv/sendMessage/${activeConv.slug}`);

    // submitted message content is encrypted.
    // since we already have it we assign the unencrypted from messageObj.
    submittedObj.content = messageObj.content;
    // messageObj.content is still not processed. it equals the rawData.
    messageElement.dataset.rawMsg = submittedObj.content;
    messageElement.dataset.auxiliaries = JSON.stringify(messageObj.auxiliaries);

    // set the unassigned attirbutes to the temporarily made message Element.
    updateMessageElement(messageElement, submittedObj);
    // unlock message controls.
    activateMessageControls(messageElement);

}


function startNewChat(){
    chatlogElement.classList.add('start-state');
    clearChatlog();
    setModel(defaultModel);
    history.replaceState(null, '', `/chat`);

    const systemPromptFields = document.querySelectorAll('.system_prompt_field');
    systemPromptFields.forEach(field => {
        field.textContent = defaultPromt;
    });

    const lastActive = document.getElementById('chats-list').querySelector('.selection-item.active');
    if(lastActive){
        lastActive.classList.remove('active')
    }

    document.getElementById('input-container').focus();

    // make sure we don't carry over any files
    const textArea = document.getElementById('main-input-field');
    textArea.dataset.files = [];
    const fileList = document.getElementById('drop-file-list');
    fileList.innerHTML = '';
    
}

function createChatItem(conv = null){

    const convItem = chatItemTemplate.content.cloneNode(true);
    const chatsList = document.getElementById('chats-list');
    const label = convItem.querySelector('.label');

    if(conv){
        convItem.querySelector('.selection-item').setAttribute('slug', conv.slug);
        label.textContent = conv.conv_name;
    }
    else{
        label.textContent = 'New Chat';
    }

    chatsList.insertBefore(convItem, chatsList.firstChild);

    return chatsList.firstElementChild;
}


async function generateChatName(firstMessage, convItem) {
    const requestObject = {
        payload: {
            model: systemModels.title_generator,
            stream: true,
            messages: [
                {
                    role: "system",
                    content: {
                        text: translation.Name_Prompt
                    }
                },
                {
                    role: "user",
                    content: {
                        text: firstMessage
                    }
                }
            ]
        },
        broadcast: false,
        threadIndex: '',
        slug: '',
    };

    
    return new Promise((resolve, reject) => {
        postData(requestObject)
            .then(response => {
                const convElement = convItem.querySelector('.label');
                let convName = ""; // Initialize to an empty string
                const onData = (data, done) => {
                    if (data) {
                        if (!data.isFinalText) {
                            convName += deconstContent(data.content).messageText;
                        } else {
                            convName = deconstContent(data.content).messageText;
                        }
                        convElement.innerText = convName;
                    }
                    if (done) {
                        resolve(convName); // Resolve the promise with convName
                    }
                };
                processStream(response.body, onData);
            })
            .catch(error => reject(error));
    });

}



async function submitConvToServer(convName) {
    // console.log(convName);
    const systemPrompt = document.querySelector('#system_prompt_field').textContent;
    const convKey = await keychainGet('aiConvKey');
    const cryptSystemPrompt = await encryptWithSymKey(convKey, systemPrompt, false);
    const systemPromptStr = JSON.stringify({
        'ciphertext':cryptSystemPrompt.ciphertext,
        'iv':cryptSystemPrompt.iv,
        'tag':cryptSystemPrompt.tag,
    });


    const requestObject = {
        conv_name: convName,
        system_prompt: systemPromptStr
    }

    try {
        const response = await fetch('/req/conv/createChat', {
            method: "POST",
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(requestObject)
        });

        const data = await response.json();

        if (data.success) {
            return data.conv;
        } else {
            // Handle unexpected response
            console.error('Unexpected response:', data);
        }
    } catch (error) {
        console.error('There was a problem with the fetch operation:', error);
    }
}


async function loadConv(btn=null, slug=null){

    abortCtrl.abort();

    if(!btn && !slug){
        return;
    }

    if(!slug) slug = btn.getAttribute('slug');
    if(!btn) btn = document.querySelector(`.selection-item[slug="${slug}"]`);
    // switchDyMainContent('chat');

    const lastActive = document.getElementById('chats-list').querySelector('.selection-item.active');
    if(lastActive){
        lastActive.classList.remove('active')
    }
    btn.classList.add('active');



    switchDyMainContent('chat');

    history.replaceState(null, '', `/chat/${slug}`);

    const convData = await RequestConvContent(slug);

    if(!convData){
        return;
    }

    clearChatlog();
    activeConv = convData;

    const convKey = await keychainGet('aiConvKey');
    const systemPromptObj = JSON.parse(convData.system_prompt);
    const systemPrompt = await decryptWithSymKey(convKey, systemPromptObj.ciphertext, systemPromptObj.iv, systemPromptObj.tag, false);

    activeConv.system_prompt = systemPrompt;

    const systemPromptFields = document.querySelectorAll('.system_prompt_field');
    systemPromptFields.forEach(field => {
        field.textContent = systemPrompt;
    });


    const msgs = convData.messages;
    for (const msg of msgs) {
        const decryptedContent =  await decryptWithSymKey(convKey, msg.content, msg.iv, msg.tag);
        msg.content = decryptedContent;
        
        // console.log(msg.content);
        const auxiliaries = msg.auxiliaries ?? []
        for (const aux of auxiliaries) {
            const decryptedContent =  await decryptWithSymKey(convKey, aux.content, aux.iv, aux.tag);
            aux.content = decryptedContent;
        }
    };

    

    if(msgs.length > 0){
        chatlogElement.classList.remove('start-state');
    }
    else{
        chatlogElement.classList.add('start-state');
    }
    loadMessagesOnGUI(convData.messages);
    scrollToLast(true);
}




async function RequestConvContent(slug){

    url = `/req/conv/${slug}`;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    try{
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
        });

        if(!response.ok){
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        return data;
    }
    catch (err){
        console.error('Error fetching data:', err);
        throw err;
    }
}



async function requestDeleteConv() {

    const confirmed = await openModal(ModalType.WARNING , translation.Cnf_deleteConv);
    if (!confirmed) {
        return;
    }

    const url = `/req/conv/removeConv/${activeConv.slug}`;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    try {
        const response = await fetch(url, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
        });
        const data = await response.json();

        if (data.success) {
            // console.log('conv removed successfully');

            const listItem = document.querySelector(`.selection-item[slug="${activeConv.slug}"]`);
            const list = listItem.parentElement;
            listItem.remove();
            // console.log(list.childElementCount);
            if(list.childElementCount > 0){
                loadConv(list.firstElementChild, null);
            }
            else{
                clearChatlog();
                chatlogElement.classList.remove('active');
                history.replaceState(null, '', `/chat`);
            }

        } else {
            console.error('Conv removal was not successful!');
        }
    } catch (error) {
        console.error('Failed to remove conv!');
    }
}


async function editConvTitle() {

    const listItem = document.querySelector(`.selection-item[slug="${activeConv.slug}"]`);
    const titleElement = listItem.querySelector('.label.singleLineTextarea');
    let currentTitle;

    if (titleElement) {
        currentTitle = titleElement.innerText; // Fetch the current title
    } else {
        return;
    }

    const messageHTML = `
    <div>
        <p>${translation.PleaseEnterTitle}:</p>
        <input type="text" style="width: 90%" id="modal-input" value="${currentTitle}" />
    </div>
    `;

    const confirmed = await openModal(ModalType.CONFIRM, messageHTML, translation.EditTitle);
    if (!confirmed) {
        return;
    }

    const url = `/req/conv/editConvTitle/${activeConv.slug}`;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const newTitle = document.getElementById("modal-input").value; 

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ conv_name: newTitle })
        });
        const data = await response.json();

        if (data.success) {
            titleElement.innerText = newTitle;
        } else {
            console.error('Conv title edit was not successful!');
        }
    } catch (error) {
        console.error(error);
        console.error('Failed to rename conv!');
    }
}

//#endregion