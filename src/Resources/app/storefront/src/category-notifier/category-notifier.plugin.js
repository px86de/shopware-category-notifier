import Plugin from 'src/plugin-system/plugin.class';
import HttpClient from 'src/service/http-client.service';

export default class CategoryNotifierPlugin extends Plugin {
    init() {
        this._client = new HttpClient();
        this._form = this.el;
        this._submitButton = this._form.querySelector('#category-notifier-submit');
        this._messageBox = this._form.querySelector('#category-notifier-message');
        this._defaultButtonLabel = this._submitButton?.textContent?.trim() || '';
        this._loadingLabel = this._submitButton?.dataset.loadingText || this._defaultButtonLabel;
        this._errorFallback = this._form.dataset.errorText || '';
        this._endpoint = this._resolveEndpoint();

        this._registerEvents();
    }

    _resolveEndpoint() {
        const action = this._form.getAttribute('action');
        if (action) {
            return action;
        }

        if (window.router && window.router['px86_category_notifier_subscribe']) {
            return window.router['px86_category_notifier_subscribe'];
        }

        return '/category-notifier/subscribe';
    }

    _registerEvents() {
        this._form.addEventListener('submit', this._onFormSubmit.bind(this));
    }

    _onFormSubmit(event) {
        event.preventDefault();

        this._hideMessage();
        this._toggleLoading(true);

        const formData = new FormData(this._form);

        this._client.post(
            this._endpoint,
            formData,
            (response, request) => {
                this._handleResponse(response, request);
            }
        );
    }

    _handleResponse(response, request) {
        let payload = null;

        if (response) {
            try {
                payload = JSON.parse(response);
            } catch (error) {
                payload = null;
            }
        }

        const isSuccessStatus = request?.status >= 200 && request.status < 300;
        const isSuccess = Boolean(payload?.success) && isSuccessStatus;
        const message = payload?.message || this._errorFallback;

        this._showMessage(message, isSuccess ? 'success' : 'danger');

        if (isSuccess) {
            this._form.reset();
        }

        this._toggleLoading(false);
    }

    _toggleLoading(isLoading) {
        if (!this._submitButton) {
            return;
        }

        if (isLoading) {
            this._submitButton.disabled = true;
            this._submitButton.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ${this._loadingLabel}`;

            return;
        }

        this._submitButton.disabled = false;
        this._submitButton.textContent = this._defaultButtonLabel;
    }

    _hideMessage() {
        if (!this._messageBox) {
            return;
        }

        this._messageBox.style.display = 'none';
    }

    _showMessage(message, type) {
        if (!this._messageBox) {
            return;
        }

        this._messageBox.className = `alert alert-${type}`;
        this._messageBox.textContent = message;
        this._messageBox.style.display = 'block';

        window.setTimeout(() => {
            this._hideMessage();
        }, 5000);
    }
}
