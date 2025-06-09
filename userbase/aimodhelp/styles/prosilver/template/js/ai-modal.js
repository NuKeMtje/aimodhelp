document.addEventListener("DOMContentLoaded", function() {

    const root = document.documentElement;
    const form = document.querySelector('#ai-modal #postForm');
    const singlePostInputDiv = document.querySelector('#ai-modal #singlePostInput');
    const topicPostsInputDiv = document.querySelector('#ai-modal #topicPostsInput');
    // const directPostInputDiv = document.getElementById('directPostInput'); // Removed
    const responseOutput = document.querySelector('#ai-modal #responseOutput');
    const evaluationDiv = document.querySelector('#ai-modal #evaluationMessage');
    const submitButton = form.querySelector('#ai-modal button[type="submit"]');
    const actionSelect = document.querySelector('#ai-modal #action');
    const outputTypeSelect = document.querySelector('#ai-modal #outputType');
    const modalOverlay = document.querySelector('#ai-modal-overlay');
    const modalContent = document.querySelector('#ai-modal #ai-modal-content');
    const modalClose = document.querySelector('#ai-modal #ai-modal-close');
    const closeButton = document.querySelector('#ai-modal .close');
    const result = document.querySelector('#ai-modal .result');

    document.querySelectorAll('.open-ai-modal').forEach(link => {
        attachAiModalClickListener(link);
    });
    
    // listen for page changes
    const observer = new MutationObserver((mutationsList) => {
        for (const mutation of mutationsList) {
            if (mutation.type === 'childList') {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType !== 1) return; // Skip non-elements

                    if (node.matches('.open-ai-modal')) {
                        attachAiModalClickListener(node);
                    } else {
                        node.querySelectorAll('.open-ai-modal').forEach((child) => {
                            attachAiModalClickListener(child);
                        });
                    }
                });
            }
        }
    });
    // Start observing
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
    
    // Close modal when clicking top close button
    modalClose.addEventListener('click', function() {
        modalOverlay.style.display = 'none';
        root.classList.remove('no-scroll');
    });
    // Close modal when clicking bototm close button (only mobile)
    closeButton.addEventListener('click', function(e) {
        modalOverlay.style.display = 'none';
        root.classList.remove('no-scroll');
    });
    // Close modal when clicking outside
    modalOverlay.addEventListener('click', function(e) {
        if (e.target === modalOverlay) {
            modalOverlay.style.display = 'none';
            root.classList.remove('no-scroll');
        }
    });

    function attachAiModalClickListener(el) {
        el.addEventListener('click', function(e) {
            e.preventDefault();
            modalOverlay.style.display = 'block';
            root.classList.add('no-scroll');
            setParams(this.dataset);
        });
    }

    /*
    Use the query parameters from the provided dataset to set the correct values in the GUI
    */
    function setParams(dataset) {
        const { postId, topicId, action, admin, days } = dataset;
        const countParam = 20;
        const outputTypeParam = null;
		const isAdmin = admin === "1";

        // Set action parameter first
        if (action) {
            if ([...actionSelect.options].some(opt => opt.value === action)) {
                actionSelect.value = action;
            } else {
                console.warn(`Action parameter '${action}' not found in action select options.`);
            }
        }

        if(!isAdmin){
            actionSelect.disabled = true;
        }

        // Set outputType parameter
        if (outputTypeParam) {
            if ([...outputTypeSelect.options].some(opt => opt.value === outputTypeParam)) {
                outputTypeSelect.value = outputTypeParam;
            }
        }

        // Set post/topic IDs and update visibility
        if (topicId) {
            document.getElementById('type_topic_posts').checked = true;
            document.getElementById('topic_id').value = topicId;
        } else if (postId) {
            document.getElementById('type_single_post').checked = true;
            document.getElementById('post_id').value = postId;
        } else {
            // Default to single post if no specific params
            document.getElementById('type_single_post').checked = true;
        }

        // Synchronize post_id and topic_id fields if both present (though only one request type can be active)
        if (postId && topicId) {
            document.getElementById('post_id').value = postId;
            document.getElementById('topic_id').value = topicId;
        }

        if (countParam) {
            document.getElementById('count').value = countParam;
        }
        if (days) {
            document.getElementById('days').value = days;
        }

        updateFormVisibility(); // Initial call to set correct visibility based on checked radio
        updateOutputTypeState(); // Initial call to set output type state based on action
        result.style.display = 'none';

        if (!isAdmin) {
            document.getElementById('outputType-group').classList.add('hidden');
        }

        // Auto-submit if t or p are present and action is set
        // Ensure actionSelect.value is correctly set before this check
        /*
            if ((topicId || postId) && action) {
                // Add a small delay to ensure DOM updates are complete
                setTimeout(() => {
                    if (form.checkValidity()) {
                        const event = new Event('submit', {
                            bubbles: true,
                            cancelable: true
                        });
                        form.dispatchEvent(event);
                    } else {
                        console.log("Form not valid for auto-submission based on URL parameters.");
                    }
                }, 100);
            }
        */

    }


    function updateFormVisibility() {
        const selectedType = form.request_type.value;
        //singlePostInputDiv.classList.toggle('hidden', selectedType !== 'single_post');
        topicPostsInputDiv.classList.toggle('hidden', selectedType !== 'topic_posts');
        // directPostInputDiv.classList.toggle('hidden', selectedType !== 'direct_post'); // Removed
        if (selectedType !== 'single_post' && selectedType !== 'topic_posts') {
            // Default to single_post if direct_post was somehow selected or no valid option
            document.getElementById('type_single_post').checked = true;
           // singlePostInputDiv.classList.remove('hidden');
            topicPostsInputDiv.classList.add('hidden');
        }
    }

    function updateOutputTypeState() {
        if (actionSelect.value === 'summarize') {
            outputTypeSelect.value = 'html';
            outputTypeSelect.disabled = true;
        } else {
            outputTypeSelect.disabled = false;
        }
    }

    actionSelect.addEventListener('change', () => {
        updateOutputTypeState();
    });

    form.request_type.forEach(radio => {
        radio.addEventListener('change', updateFormVisibility);
    });


    submitButton.addEventListener('click', async function(e) {
        //form.addEventListener('submit', async (e) => {
        e.preventDefault();
        evaluationDiv.innerHTML = '<em>Verzoek wordt verwerkt, even geduld alstublieft...</em>';
        submitButton.disabled = true;
        submitButton.textContent = 'Bezig met verwerken...';
        responseOutput.textContent = ''; // Clear previous raw response

        const action = form.action.value;
        const requestType = form.request_type.value;

        let payload = {
            action: action,
        };

        if (requestType === 'single_post') {
            const postId = form.post_id.value.trim();
            if (!postId) {
                alert('Voer een bericht-ID in.');
                finalizeSubmit();
                return;
            }
            payload.post_id = postId;
        } else if (requestType === 'topic_posts') {
            const topicId = form.topic_id.value.trim();
            // const forumId = form.forum_id.value.trim(); // Removed
            if (!topicId) { // Only topicId is mandatory now for this type
                alert('Voer een onderwerp-ID in.');
                finalizeSubmit();
                return;
            }
            payload.topic_id = topicId;
            // payload.forum_id = forumId; // Removed
            if (form.count.value.trim()) payload.count = form.count.value.trim();
            if (form.days.value.trim()) payload.days = form.days.value.trim();
            payload.outputType = form.outputType.value;
            // } else if (requestType === 'direct_post') { // Removed direct_post logic
            // const forumPostContent = form.forum_post.value.trim();
            // if (!forumPostContent) {
            // alert('Please enter forum post content.');
            // finalizeSubmit(); return;
            // }
            // payload.forum_post = forumPostContent;
        } else {
            alert('Invalid request type selected.');
            finalizeSubmit();
            return;
        }

        try {
            const response = await fetch('ext/userbase/aimodhelp/service/AiServiceApi.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const rawText = await response.text();
            evaluationDiv.innerHTML = ''; // Clear processing message

            const data = safeParseJSON(rawText, 'response json parse', responseOutput);
            safeStringify(data, 'response data', responseOutput); // Display full JSON response

            if (data) {
                let message = '';
                if (data.evaluation && data.evaluation.message) {
                    message = data.evaluation.message;
                } else if (data.summary && data.summary.message) {
                    message = data.summary.message;
                } else if (data.error) {
                    message = `<strong style="color:red;">Error:</strong> ${data.error}`;
                    if (data.logs && data.logs.length > 0) {
                        message += '<br><br><strong>Logs:</strong><br>' + data.logs.join('<br>');
                    }
                } else if (data.message) { // For other success messages
                    message = data.message;
                }


                if (message) {
                    message = message.replace(/^```html\s*/, '').replace(/```$/, '');
                    if (/^<!DOCTYPE html>/i.test(message.trim()) || /^<html.*>/i.test(message.trim())) {
                        evaluationDiv.innerHTML = '<pre style="white-space: pre-wrap; word-wrap: break-word;">' +
                            message.replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>') + '</pre>';
                    } else {
                        evaluationDiv.innerHTML = message;
                    }
                } else if (!data.error && !data.evaluation && !data.summary) {
                    evaluationDiv.innerHTML = 'Verzoek verwerkt. Geen specifieke evaluatie of samenvattingsbericht beschikbaar.';
                }
            } else {
                evaluationDiv.innerHTML = 'Serverantwoord kon niet worden verwerkt. Controleer de onbewerkte uitvoer hieronder.';
            }

        } catch (error) {
            responseOutput.textContent = 'Fetch Error: ' + error.message;
            evaluationDiv.innerHTML = `<strong style="color:red;">Client-side Error: ${error.message}</strong>`;
            console.error("Fetch error:", error);
        } finally {
            result.style.display = 'block';
            finalizeSubmit();
        }
    });

    function finalizeSubmit() {
        submitButton.disabled = false;
        submitButton.textContent = 'Verstuur';
    }

    function safeStringify(data, label, outputElement) {
        try {
            outputElement.textContent = JSON.stringify(data, null, 2);
        } catch (err) {
            outputElement.textContent = `Error stringifying JSON at ${label}: ${err.message}`;
            console.error(`Error stringifying JSON at ${label}:`, err, data);
        }
    }

    function safeParseJSON(text, label, outputElement) {
        try {
            if (!text || text.trim() === '') {
                outputElement.textContent += `\nWarning: Empty response text at ${label}`;
                return null;
            }
            return JSON.parse(text);
        } catch (err) {
            outputElement.textContent += `\nError parsing JSON at ${label}: ${err.message}\nRaw text: ${text}`;
            console.error(`Error parsing JSON at ${label}:`, err, `Raw text: ${text}`);
            return null;
        }
    }
});