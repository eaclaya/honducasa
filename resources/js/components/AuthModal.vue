<script setup lang="ts">
import { Form, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import GoogleAuthButton from '@/components/GoogleAuthButton.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store as loginStore } from '@/routes/login';
import { request as requestPasswordReset } from '@/routes/password';
import { store as registerStore } from '@/routes/register';

const props = defineProps<{
    description?: string;
}>();

const open = defineModel<boolean>('open', { default: false });

const page = usePage();
const tr = (es: string, en: string): string =>
    page.props.locale === 'es' ? es : en;

const mode = ref<'login' | 'register'>('login');
const formKey = ref(0);

const currentUrl = computed(() => page.url);

const handleOpenChange = (value: boolean): void => {
    open.value = value;

    if (!value) {
        mode.value = 'login';
        formKey.value++;
    }
};

const handleSuccess = (): void => {
    open.value = false;
};
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent
            class="max-h-[90vh] gap-6 overflow-y-auto rounded-2xl border-border bg-card p-6 text-card-foreground shadow-2xl sm:max-w-md"
        >
            <DialogHeader>
                <DialogTitle>
                    {{
                        mode === 'login'
                            ? tr('Inicia sesión', 'Log in')
                            : tr('Crea una cuenta', 'Create an account')
                    }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        props.description ??
                        tr(
                            'Inicia sesión o crea una cuenta para continuar.',
                            'Log in or create an account to continue.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4">
                <GoogleAuthButton :redirect="currentUrl" />
                <InputError :message="$page.props.errors.google" />
                <div class="relative text-center text-sm">
                    <span
                        class="relative z-10 bg-background px-2 text-muted-foreground"
                    >
                        {{
                            tr('O continúa manualmente', 'Or continue manually')
                        }}
                    </span>
                    <span class="absolute inset-x-0 top-1/2 border-t" />
                </div>
            </div>

            <Form
                v-if="mode === 'login'"
                :key="`login-${formKey}`"
                v-bind="loginStore.form()"
                :reset-on-success="['password']"
                v-slot="{ errors, processing }"
                class="flex flex-col gap-6"
                @success="handleSuccess"
            >
                <input type="hidden" name="redirect" :value="currentUrl" />
                <div class="grid gap-6">
                    <div class="grid gap-2">
                        <Label for="auth-modal-email">{{
                            tr('Correo electrónico', 'Email address')
                        }}</Label>
                        <Input
                            id="auth-modal-email"
                            type="email"
                            name="email"
                            required
                            autofocus
                            autocomplete="email"
                            placeholder="email@example.com"
                            class="h-12 rounded-[10px] border-input bg-background px-4 text-foreground"
                        />
                        <InputError :message="errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <div class="flex items-center justify-between">
                            <Label for="auth-modal-password">{{
                                tr('Contraseña', 'Password')
                            }}</Label>
                            <TextLink
                                :href="requestPasswordReset()"
                                class="text-sm"
                            >
                                {{
                                    tr(
                                        '¿Olvidaste tu contraseña?',
                                        'Forgot password?',
                                    )
                                }}
                            </TextLink>
                        </div>
                        <PasswordInput
                            id="auth-modal-password"
                            name="password"
                            required
                            autocomplete="current-password"
                            :placeholder="tr('Contraseña', 'Password')"
                        />
                        <InputError :message="errors.password" />
                    </div>

                    <Label
                        for="auth-modal-remember"
                        class="flex items-center space-x-3"
                    >
                        <Checkbox id="auth-modal-remember" name="remember" />
                        <span>{{ tr('Recuérdame', 'Remember me') }}</span>
                    </Label>

                    <Button type="submit" class="w-full" :disabled="processing">
                        <Spinner v-if="processing" />
                        {{ tr('Iniciar sesión', 'Log in') }}
                    </Button>
                </div>
            </Form>

            <Form
                v-else
                :key="`register-${formKey}`"
                v-bind="registerStore.form()"
                :reset-on-success="['password', 'password_confirmation']"
                v-slot="{ errors, processing }"
                class="flex flex-col gap-6"
                @success="handleSuccess"
            >
                <input type="hidden" name="redirect" :value="currentUrl" />
                <div class="grid gap-6">
                    <div class="grid gap-2">
                        <Label for="auth-modal-name">{{
                            tr('Nombre', 'Name')
                        }}</Label>
                        <Input
                            id="auth-modal-name"
                            type="text"
                            name="name"
                            required
                            autofocus
                            autocomplete="name"
                            :placeholder="tr('Nombre completo', 'Full name')"
                            class="h-12 rounded-[10px] border-input bg-background px-4 text-foreground"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="auth-modal-register-email">{{
                            tr('Correo electrónico', 'Email address')
                        }}</Label>
                        <Input
                            id="auth-modal-register-email"
                            type="email"
                            name="email"
                            required
                            autocomplete="email"
                            placeholder="email@example.com"
                            class="h-12 rounded-[10px] border-input bg-background px-4 text-foreground"
                        />
                        <InputError :message="errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="auth-modal-register-password">{{
                            tr('Contraseña', 'Password')
                        }}</Label>
                        <PasswordInput
                            id="auth-modal-register-password"
                            name="password"
                            required
                            autocomplete="new-password"
                            :placeholder="tr('Contraseña', 'Password')"
                        />
                        <InputError :message="errors.password" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="auth-modal-password-confirmation">{{
                            tr('Confirmar contraseña', 'Confirm password')
                        }}</Label>
                        <PasswordInput
                            id="auth-modal-password-confirmation"
                            name="password_confirmation"
                            required
                            autocomplete="new-password"
                            :placeholder="
                                tr('Confirmar contraseña', 'Confirm password')
                            "
                        />
                        <InputError :message="errors.password_confirmation" />
                    </div>

                    <Button type="submit" class="w-full" :disabled="processing">
                        <Spinner v-if="processing" />
                        {{ tr('Crear cuenta', 'Create account') }}
                    </Button>
                </div>
            </Form>

            <div class="text-center text-sm text-muted-foreground">
                <template v-if="mode === 'login'">
                    {{ tr('¿No tienes una cuenta?', "Don't have an account?") }}
                    <button
                        type="button"
                        class="font-medium text-foreground underline underline-offset-4"
                        @click="mode = 'register'"
                    >
                        {{ tr('Regístrate', 'Sign up') }}
                    </button>
                </template>
                <template v-else>
                    {{
                        tr('¿Ya tienes una cuenta?', 'Already have an account?')
                    }}
                    <button
                        type="button"
                        class="font-medium text-foreground underline underline-offset-4"
                        @click="mode = 'login'"
                    >
                        {{ tr('Inicia sesión', 'Log in') }}
                    </button>
                </template>
            </div>
        </DialogContent>
    </Dialog>
</template>
