<script lang="ts">
type CredentialResponse = {
    credential: string;
};

type GoogleIdentity = {
    accounts: {
        id: {
            cancel: () => void;
            initialize: (configuration: {
                client_id: string;
                callback: (response: CredentialResponse) => void;
                context: 'signin';
            }) => void;
            prompt: () => void;
        };
    };
};

declare global {
    interface Window {
        google?: GoogleIdentity;
    }
}

let initializedClientId: string | null = null;
let credentialHandler: ((response: CredentialResponse) => void) | null = null;
</script>

<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted } from 'vue';
import { oneTap } from '@/routes/auth/google';

const page = usePage();
const scriptId = 'google-identity-services';

const submitCredential = (response: CredentialResponse): void => {
    router.post(
        oneTap.url(),
        {
            credential: response.credential,
            redirect: `${window.location.pathname}${window.location.search}${window.location.hash}`,
        },
        {
            preserveScroll: true,
        },
    );
};

const prompt = (): void => {
    const clientId = page.props.googleOneTap.clientId;

    if (!clientId || page.props.auth.user || !window.google) {
        return;
    }

    credentialHandler = submitCredential;

    if (initializedClientId !== clientId) {
        window.google.accounts.id.initialize({
            client_id: clientId,
            callback: (response) => credentialHandler?.(response),
            context: 'signin',
        });
        initializedClientId = clientId;
    }

    window.google.accounts.id.prompt();
};

onMounted(() => {
    if (!page.props.googleOneTap.clientId || page.props.auth.user) {
        return;
    }

    const existingScript = document.getElementById(
        scriptId,
    ) as HTMLScriptElement | null;

    if (existingScript) {
        if (window.google) {
            prompt();
        } else {
            existingScript.addEventListener('load', prompt, { once: true });
        }

        return;
    }

    const script = document.createElement('script');
    script.id = scriptId;
    script.src = 'https://accounts.google.com/gsi/client';
    script.async = true;
    script.defer = true;
    script.addEventListener('load', prompt, { once: true });
    document.head.appendChild(script);
});

onBeforeUnmount(() => {
    credentialHandler = null;
    window.google?.accounts.id.cancel();
});
</script>

<template>
    <span class="hidden" aria-hidden="true" />
</template>
