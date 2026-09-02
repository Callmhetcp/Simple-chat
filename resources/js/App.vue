<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';

const mode = ref('login');

const name = ref('');
const username = ref('');
const email = ref('');
const password = ref('');
const passwordConfirmation = ref('');

const user = ref(null);
const token = ref(localStorage.getItem('auth_token'));

const loading = ref(false);
const error = ref('');
const success = ref('');

const conversations = ref([]);
const activeConversation = ref(null);
const messages = ref([]);

const messageBody = ref('');

const searchQuery = ref('');
const searchedUsers = ref([]);
const searchingUsers = ref(false);

const loadingConversations = ref(false);
const loadingMessages = ref(false);
const sendingMessage = ref(false);

const editingMessage = ref(null);
const editingBody = ref('');

const deletingMessageId = ref(null);

const reactingMessageId = ref(null);

const showEmojiPicker = ref(false);

const emojis = [
    '😀',
    '😃',
    '😄',
    '😁',
    '😆',
    '😅',
    '😂',
    '🤣',
    '😊',
    '😇',
    '🙂',
    '🙃',
    '😉',
    '😌',
    '😍',
    '🥰',
    '😘',
    '😎',
    '🤩',
    '🥳',
    '🤔',
    '😐',
    '😑',
    '😶',
    '🙄',
    '😏',
    '😢',
    '😭',
    '😡',
    '🤬',
    '😱',
    '😴',
    '👍',
    '👎',
    '👏',
    '🙏',
    '❤️',
    '🔥',
    '🎉',
    '💯',
    '💔',
    '✨',
    '⭐',
    '✅',
    '❌',
];

const reactionTypes = [
    '❤️',
    '😂',
    '😮',
    '😢',
    '😡',
    '👍',
];

const isLogin = computed(() => mode.value === 'login');

const getOtherUser = (conversation) => {
    if (!conversation?.users) {
        return null;
    }

    return (
        conversation.users.find(
            (conversationUser) =>
                Number(conversationUser.id) !== Number(user.value?.id),
        ) ??
        conversation.users[0] ??
        null
    );
};

const getUserInitial = (selectedUser) => {
    return (
        selectedUser?.name
            ?.charAt(0)
            ?.toUpperCase() || '?'
    );
};

const apiHeaders = () => ({
    Accept: 'application/json',
    Authorization: `Bearer ${token.value}`,
});

const resetMessages = () => {
    error.value = '';
    success.value = '';
};

const switchMode = (newMode) => {
    mode.value = newMode;

    name.value = '';
    username.value = '';
    email.value = '';
    password.value = '';
    passwordConfirmation.value = '';

    resetMessages();
};

const handleLogin = async () => {
    resetMessages();
    loading.value = true;

    try {
        const response = await fetch('/api/login', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                email: email.value,
                password: password.value,
            }),
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Login failed.');
        }

        localStorage.setItem('auth_token', data.token);

        token.value = data.token;
        user.value = data.user;

        password.value = '';

        success.value = 'Login successful.';

        await loadConversations();
    } catch (err) {
        error.value = err.message || 'Something went wrong.';
    } finally {
        loading.value = false;
    }
};

const handleRegister = async () => {
    resetMessages();
    loading.value = true;

    try {
        const response = await fetch('/api/register', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                name: name.value,
                username: username.value,
                email: email.value,
                password: password.value,
                password_confirmation:
                    passwordConfirmation.value,
            }),
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(
                data.message || 'Registration failed.',
            );
        }

        localStorage.setItem('auth_token', data.token);

        token.value = data.token;
        user.value = data.user;

        success.value = 'Account created successfully.';

        name.value = '';
        username.value = '';
        email.value = '';
        password.value = '';
        passwordConfirmation.value = '';

        await loadConversations();
    } catch (err) {
        error.value = err.message || 'Something went wrong.';
    } finally {
        loading.value = false;
    }
};

const loadUser = async () => {
    if (!token.value) {
        return;
    }

    try {
        const response = await fetch('/api/user', {
            headers: apiHeaders(),
        });

        if (!response.ok) {
            throw new Error('Session expired.');
        }

        user.value = await response.json();

        await loadConversations();
    } catch {
        localStorage.removeItem('auth_token');

        token.value = null;
        user.value = null;
    }
};

const loadConversations = async () => {
    if (!token.value) {
        return;
    }

    loadingConversations.value = true;

    try {
        const response = await fetch('/api/conversations', {
            headers: apiHeaders(),
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(
                data.message || 'Failed to load conversations.',
            );
        }

        conversations.value = data.data?.data ?? [];
    } catch (err) {
        error.value =
            err.message || 'Failed to load conversations.';
    } finally {
        loadingConversations.value = false;
    }
};

const selectConversation = async (conversation) => {
    if (!conversation?.id) {
        return;
    }

    activeConversation.value = conversation;

    showEmojiPicker.value = false;

    cancelEditing();

    subscribeToConversation(conversation);

    await loadMessages(conversation);
};

const closeMobileChat = () => {
    activeConversation.value = null;
    messages.value = [];

    showEmojiPicker.value = false;

    cancelEditing();

    leaveCurrentChannel();
};

const loadMessages = async (conversation) => {
    loadingMessages.value = true;
    messages.value = [];

    try {
        const response = await fetch(
            `/api/conversations/${conversation.id}/messages`,
            {
                headers: apiHeaders(),
            },
        );

        const data = await response.json();

        if (!response.ok) {
            throw new Error(
                data.message || 'Failed to load messages.',
            );
        }

        messages.value = (
            data.data?.data ??
            data.data ??
            []
        ).map((message) => ({
            ...message,
            reactions: message.reactions ?? [],
        }));
    } catch (err) {
        error.value =
            err.message || 'Failed to load messages.';
    } finally {
        loadingMessages.value = false;
    }
};

const searchUsers = () => {
    clearTimeout(searchTimeout);

    const query = searchQuery.value.trim();

    if (!query) {
        searchedUsers.value = [];
        searchingUsers.value = false;
        return;
    }

    searchingUsers.value = true;

    searchTimeout = setTimeout(async () => {
        try {
            const response = await fetch(
                `/api/users?search=${encodeURIComponent(query)}`,
                {
                    headers: apiHeaders(),
                },
            );

            const data = await response.json();

            if (!response.ok) {
                throw new Error(
                    data.message || 'Failed to search users.',
                );
            }

            searchedUsers.value = data.data ?? [];
        } catch (err) {
            error.value =
                err.message || 'Failed to search users.';

            searchedUsers.value = [];
        } finally {
            searchingUsers.value = false;
        }
    }, 300);
};

const openUserConversation = async (selectedUser) => {
    if (!selectedUser?.id) {
        return;
    }

    resetMessages();

    loadingConversations.value = true;

    try {
        const response = await fetch('/api/conversations', {
            method: 'POST',
            headers: {
                ...apiHeaders(),
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                user_id: selectedUser.id,
            }),
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(
                data.message ||
                    'Failed to open conversation.',
            );
        }

        const conversation = data.conversation;

        const existingIndex =
            conversations.value.findIndex(
                (item) => item.id === conversation.id,
            );

        if (existingIndex === -1) {
            conversations.value.unshift(conversation);
        } else {
            conversations.value[existingIndex] = {
                ...conversations.value[existingIndex],
                ...conversation,
            };
        }

        searchQuery.value = '';
        searchedUsers.value = [];

        const selectedConversation =
            conversations.value.find(
                (item) => item.id === conversation.id,
            );

        await selectConversation(
            selectedConversation ?? conversation,
        );
    } catch (err) {
        error.value =
            err.message ||
            'Failed to open conversation.';
    } finally {
        loadingConversations.value = false;
    }
};

const sendMessage = async () => {
    const body = messageBody.value.trim();

    if (
        !body ||
        !activeConversation.value ||
        sendingMessage.value
    ) {
        return;
    }

    sendingMessage.value = true;

    resetMessages();

    try {
        const response = await fetch(
            `/api/conversations/${activeConversation.value.id}/messages`,
            {
                method: 'POST',
                headers: {
                    ...apiHeaders(),
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    body,
                }),
            },
        );

        const data = await response.json();

        if (!response.ok) {
            throw new Error(
                data.message || 'Failed to send message.',
            );
        }

        const sentMessage = data.data;

        if (
            sentMessage &&
            typeof sentMessage === 'object' &&
            !messages.value.some(
                (message) =>
                    message.id === sentMessage.id,
            )
        ) {
            messages.value.push({
                ...sentMessage,
                reactions:
                    sentMessage.reactions ?? [],
            });
        }

        messageBody.value = '';
        showEmojiPicker.value = false;

        await loadConversations();

        const refreshedConversation =
            conversations.value.find(
                (conversation) =>
                    conversation.id ===
                    activeConversation.value.id,
            );

        if (refreshedConversation) {
            activeConversation.value =
                refreshedConversation;
        }
    } catch (err) {
        error.value =
            err.message || 'Failed to send message.';
    } finally {
        sendingMessage.value = false;
    }
};

const addEmoji = (emoji) => {
    messageBody.value += emoji;
    showEmojiPicker.value = false;
};

const toggleEmojiPicker = () => {
    showEmojiPicker.value =
        !showEmojiPicker.value;
};

const startEditingMessage = (message) => {
    if (!isMyMessage(message)) {
        return;
    }

    editingMessage.value = message;
    editingBody.value = message.body;

    showEmojiPicker.value = false;
};

const cancelEditing = () => {
    editingMessage.value = null;
    editingBody.value = '';
};

const updateMessage = async () => {
    const body = editingBody.value.trim();

    if (!body || !editingMessage.value) {
        return;
    }

    const message = editingMessage.value;

    try {
        const response = await fetch(
            `/api/conversations/${activeConversation.value.id}/messages/${message.id}`,
            {
                method: 'PATCH',
                headers: {
                    ...apiHeaders(),
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    body,
                }),
            },
        );

        const data = await response.json();

        if (!response.ok) {
            throw new Error(
                data.message ||
                    'Failed to update message.',
            );
        }

        const updatedMessage =
            data.data ?? data.message;

        const index = messages.value.findIndex(
            (item) => item.id === message.id,
        );

        if (
            index !== -1 &&
            updatedMessage &&
            typeof updatedMessage === 'object'
        ) {
            messages.value[index] = {
                ...updatedMessage,
                reactions:
                    updatedMessage.reactions ??
                    messages.value[index].reactions ??
                    [],
            };
        } else if (index !== -1) {
            messages.value[index].body = body;
        }

        cancelEditing();

        await loadConversations();
    } catch (err) {
        error.value =
            err.message ||
            'Failed to update message.';
    }
};

const deleteMessage = async (message) => {
    if (!isMyMessage(message)) {
        return;
    }

    if (
        !window.confirm(
            'Delete this message?',
        )
    ) {
        return;
    }

    deletingMessageId.value = message.id;

    try {
        const response = await fetch(
            `/api/conversations/${activeConversation.value.id}/messages/${message.id}`,
            {
                method: 'DELETE',
                headers: apiHeaders(),
            },
        );

        const data = await response.json();

        if (!response.ok) {
            throw new Error(
                data.message ||
                    'Failed to delete message.',
            );
        }

        messages.value =
            messages.value.filter(
                (item) =>
                    item.id !== message.id,
            );

        if (
            editingMessage.value?.id ===
            message.id
        ) {
            cancelEditing();
        }

        await loadConversations();
    } catch (err) {
        error.value =
            err.message ||
            'Failed to delete message.';
    } finally {
        deletingMessageId.value = null;
    }
};

/*
|--------------------------------------------------------------------------
| Reactions
|--------------------------------------------------------------------------
*/

const getMessageReactions = (message) => {
    return Array.isArray(message?.reactions)
        ? message.reactions
        : [];
};

const getReactionCount = (message, type) => {
    return getMessageReactions(message).filter(
        (reaction) => reaction.type === type,
    ).length;
};

const hasReacted = (message, type) => {
    if (!user.value) {
        return false;
    }

    return getMessageReactions(message).some(
        (reaction) =>
            reaction.type === type &&
            Number(reaction.user_id) ===
                Number(user.value.id),
    );
};

const addLocalReaction = (message, reaction) => {
    if (!Array.isArray(message.reactions)) {
        message.reactions = [];
    }

    const existingIndex =
        message.reactions.findIndex(
            (item) => item.id === reaction.id,
        );

    if (existingIndex === -1) {
        message.reactions.push(reaction);
    } else {
        message.reactions[existingIndex] = reaction;
    }
};

const removeLocalReaction = (
    message,
    reaction,
) => {
    if (!Array.isArray(message.reactions)) {
        message.reactions = [];
    }

    message.reactions =
        message.reactions.filter(
            (item) =>
                !(
                    Number(item.user_id) ===
                        Number(reaction.user_id) &&
                    item.type === reaction.type
                ),
        );
};

const findMessage = (messageId) => {
    return messages.value.find(
        (message) =>
            Number(message.id) ===
            Number(messageId),
    );
};

const toggleReaction = async (
    message,
    type = '❤️',
) => {
    if (
        !activeConversation.value ||
        reactingMessageId.value === message.id
    ) {
        return;
    }

    reactingMessageId.value = message.id;
    resetMessages();

    const alreadyReacted = hasReacted(
        message,
        type,
    );

    try {
        if (alreadyReacted) {
            const response = await fetch(
                `/api/conversations/${activeConversation.value.id}/messages/${message.id}/reactions`,
                {
                    method: 'DELETE',
                    headers: {
                        ...apiHeaders(),
                        'Content-Type':
                            'application/json',
                    },
                    body: JSON.stringify({
                        type,
                    }),
                },
            );

            const data =
                await response.json();

            if (!response.ok) {
                throw new Error(
                    data.message ||
                        'Failed to remove reaction.',
                );
            }

            removeLocalReaction(message, {
                user_id: user.value.id,
                type,
            });
        } else {
            const response = await fetch(
                `/api/conversations/${activeConversation.value.id}/messages/${message.id}/reactions`,
                {
                    method: 'POST',
                    headers: {
                        ...apiHeaders(),
                        'Content-Type':
                            'application/json',
                    },
                    body: JSON.stringify({
                        type,
                    }),
                },
            );

            const data =
                await response.json();

            if (!response.ok) {
                throw new Error(
                    data.message ||
                        'Failed to add reaction.',
                );
            }

            if (data.data) {
                addLocalReaction(
                    message,
                    data.data,
                );
            }
        }
    } catch (err) {
        error.value =
            err.message ||
            'Failed to update reaction.';
    } finally {
        reactingMessageId.value = null;
    }
};

const isMyMessage = (message) => {
    return (
        Number(message?.sender_id) ===
        Number(user.value?.id)
    );
};

const logout = async () => {
    resetMessages();
    loading.value = true;

    try {
        if (token.value) {
            await fetch('/api/logout', {
                method: 'POST',
                headers: apiHeaders(),
            });
        }
    } finally {
        leaveCurrentChannel();

        localStorage.removeItem('auth_token');

        token.value = null;
        user.value = null;

        conversations.value = [];
        activeConversation.value = null;
        messages.value = [];

        loading.value = false;

        success.value =
            'You have been logged out.';
    }
};

const formatMessageTime = (date) => {
    if (!date) {
        return '';
    }

    const parsedDate = new Date(date);

    if (Number.isNaN(parsedDate.getTime())) {
        return '';
    }

    return new Intl.DateTimeFormat(
        'en-NG',
        {
            hour: 'numeric',
            minute: '2-digit',
        },
    ).format(parsedDate);
};

const formatConversationTime = (date) => {
    if (!date) {
        return '';
    }

    const messageDate = new Date(date);

    if (Number.isNaN(messageDate.getTime())) {
        return '';
    }

    const today = new Date();

    if (
        messageDate.toDateString() ===
        today.toDateString()
    ) {
        return formatMessageTime(date);
    }

    return new Intl.DateTimeFormat(
        'en-NG',
        {
            day: 'numeric',
            month: 'short',
        },
    ).format(messageDate);
};

const getConversationPreview = (
    conversation,
) => {
    return (
        conversation?.latest_message?.body ??
        conversation?.last_message?.body ??
        'No messages yet'
    );
};

let searchTimeout = null;
let echoChannel = null;

const subscribeToConversation = (
    conversation,
) => {
    if (!window.Echo || !conversation) {
        return;
    }

    leaveCurrentChannel();

    echoChannel = conversation.id;

    window.Echo.private(
        `conversation.${conversation.id}`,
    )
        .listen(
            '.message.sent',
            (message) => {
                if (
                    !messages.value.some(
                        (existingMessage) =>
                            Number(
                                existingMessage.id,
                            ) ===
                            Number(message.id),
                    )
                ) {
                    messages.value.push({
                        ...message,
                        reactions:
                            message.reactions ??
                            [],
                    });
                }

                const conversationIndex =
                    conversations.value.findIndex(
                        (item) =>
                            Number(item.id) ===
                            Number(
                                conversation.id,
                            ),
                    );

                if (
                    conversationIndex !== -1
                ) {
                    conversations.value[
                        conversationIndex
                    ].latest_message =
                        message;
                }
            },
        )
        .listen(
            '.reaction.added',
            (reaction) => {
                const message =
                    findMessage(
                        reaction.message_id,
                    );

                if (!message) {
                    return;
                }

                addLocalReaction(
                    message,
                    reaction,
                );
            },
        )
        .listen(
            '.reaction.removed',
            (reaction) => {
                const message =
                    findMessage(
                        reaction.message_id,
                    );

                if (!message) {
                    return;
                }

                removeLocalReaction(
                    message,
                    reaction,
                );
            },
        );
};

const leaveCurrentChannel = () => {
    if (!window.Echo || !echoChannel) {
        echoChannel = null;
        return;
    }

    window.Echo.leave(
        `conversation.${echoChannel}`,
    );

    echoChannel = null;
};

onMounted(async () => {
    await loadUser();
});

onUnmounted(() => {
    clearTimeout(searchTimeout);
    leaveCurrentChannel();
});
</script>

<template>
    <main
        class="h-[100dvh] overflow-hidden bg-slate-950 text-white"
    >
        <!-- =========================================================
             AUTHENTICATION
        ========================================================== -->

        <section
            v-if="!user"
            class="flex h-[100dvh] items-center justify-center overflow-y-auto px-4 py-8"
        >
            <div class="w-full max-w-md">
                <div class="mb-8 text-center">
                    <p
                        class="text-sm font-bold tracking-[0.25em] text-emerald-400"
                    >
                        SIMPLE CHAT
                    </p>

                    <h1
                        class="mt-3 text-3xl font-bold sm:text-4xl"
                    >
                        {{
                            isLogin
                                ? 'Welcome back'
                                : 'Create your account'
                        }}
                    </h1>

                    <p
                        class="mx-auto mt-3 max-w-sm text-sm leading-6 text-slate-400"
                    >
                        {{
                            isLogin
                                ? 'Sign in to continue to your conversations.'
                                : 'Create an account and start chatting privately.'
                        }}
                    </p>
                </div>

                <div
                    class="rounded-2xl border border-slate-800 bg-slate-900 p-6 shadow-2xl sm:p-8"
                >
                    <div
                        v-if="error"
                        class="mb-5 rounded-xl border border-red-900 bg-red-950/50 px-4 py-3 text-sm text-red-300"
                    >
                        {{ error }}
                    </div>

                    <div
                        v-if="success"
                        class="mb-5 rounded-xl border border-emerald-900 bg-emerald-950/50 px-4 py-3 text-sm text-emerald-300"
                    >
                        {{ success }}
                    </div>

                    <!-- LOGIN -->

                    <form
                        v-if="isLogin"
                        @submit.prevent="handleLogin"
                    >
                        <label
                            class="mb-2 block text-sm font-medium text-slate-300"
                        >
                            Email
                        </label>

                        <input
                            v-model="email"
                            type="email"
                            autocomplete="email"
                            required
                            class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-white outline-none transition focus:border-emerald-500"
                            placeholder="you@example.com"
                        />

                        <label
                            class="mb-2 mt-5 block text-sm font-medium text-slate-300"
                        >
                            Password
                        </label>

                        <input
                            v-model="password"
                            type="password"
                            autocomplete="current-password"
                            required
                            class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-white outline-none transition focus:border-emerald-500"
                            placeholder="••••••••"
                        />

                        <button
                            type="submit"
                            :disabled="loading"
                            class="mt-6 w-full rounded-xl bg-emerald-500 px-4 py-3 font-bold text-slate-950 transition hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{
                                loading
                                    ? 'Signing in...'
                                    : 'Sign In'
                            }}
                        </button>
                    </form>

                    <!-- REGISTER -->

                    <form
                        v-else
                        @submit.prevent="handleRegister"
                    >
                        <label
                            class="mb-2 block text-sm font-medium text-slate-300"
                        >
                            Full Name
                        </label>

                        <input
                            v-model="name"
                            type="text"
                            autocomplete="name"
                            required
                            class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-white outline-none transition focus:border-emerald-500"
                            placeholder="John Doe"
                        />

                        <label
                            class="mb-2 mt-5 block text-sm font-medium text-slate-300"
                        >
                            Username
                        </label>

                        <input
                            v-model="username"
                            type="text"
                            autocomplete="username"
                            required
                            class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-white outline-none transition focus:border-emerald-500"
                            placeholder="johndoe"
                        />

                        <label
                            class="mb-2 mt-5 block text-sm font-medium text-slate-300"
                        >
                            Email
                        </label>

                        <input
                            v-model="email"
                            type="email"
                            autocomplete="email"
                            required
                            class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-white outline-none transition focus:border-emerald-500"
                            placeholder="you@example.com"
                        />

                        <label
                            class="mb-2 mt-5 block text-sm font-medium text-slate-300"
                        >
                            Password
                        </label>

                        <input
                            v-model="password"
                            type="password"
                            autocomplete="new-password"
                            required
                            class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-white outline-none transition focus:border-emerald-500"
                            placeholder="Minimum 8 characters"
                        />

                        <label
                            class="mb-2 mt-5 block text-sm font-medium text-slate-300"
                        >
                            Confirm Password
                        </label>

                        <input
                            v-model="passwordConfirmation"
                            type="password"
                            autocomplete="new-password"
                            required
                            class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-white outline-none transition focus:border-emerald-500"
                            placeholder="Repeat your password"
                        />

                        <button
                            type="submit"
                            :disabled="loading"
                            class="mt-6 w-full rounded-xl bg-emerald-500 px-4 py-3 font-bold text-slate-950 transition hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{
                                loading
                                    ? 'Creating account...'
                                    : 'Create Account'
                            }}
                        </button>
                    </form>

                    <div
                        class="mt-6 border-t border-slate-800 pt-6 text-center text-sm text-slate-400"
                    >
                        <template v-if="isLogin">
                            Don't have an account?

                            <button
                                type="button"
                                class="ml-1 font-semibold text-emerald-400 hover:text-emerald-300"
                                @click="
                                    switchMode(
                                        'register',
                                    )
                                "
                            >
                                Create account
                            </button>
                        </template>

                        <template v-else>
                            Already have an account?

                            <button
                                type="button"
                                class="ml-1 font-semibold text-emerald-400 hover:text-emerald-300"
                                @click="
                                    switchMode(
                                        'login',
                                    )
                                "
                            >
                                Sign in
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </section>

        <!-- =========================================================
             CHAT APPLICATION
        ========================================================== -->

        <section
            v-else
            class="h-[100dvh] overflow-hidden"
        >
            <div class="flex h-full min-h-0 w-full">

                <!-- =================================================
                     CONVERSATION SIDEBAR
                     MOBILE: full screen unless chat is open
                     TABLET/DESKTOP: always visible
                ================================================== -->

                <aside
                    class="relative h-full w-full shrink-0 flex-col border-r border-slate-800 bg-slate-900 md:flex md:w-[320px] lg:w-[380px] xl:w-[420px]"
                    :class="
                        activeConversation
                            ? 'hidden md:flex'
                            : 'flex'
                    "
                >
                    <!-- SIDEBAR HEADER -->

                    <div
                        class="shrink-0 border-b border-slate-800 bg-slate-900 px-4 py-4"
                    >
                        <div
                            class="flex items-center justify-between gap-3"
                        >
                            <div class="min-w-0">
                                <p
                                    class="text-[10px] font-bold tracking-[0.25em] text-emerald-400"
                                >
                                    SIMPLE CHAT
                                </p>

                                <h1
                                    class="mt-1 text-xl font-bold"
                                >
                                    Chats
                                </h1>
                            </div>

                            <button
                                type="button"
                                class="shrink-0 rounded-xl px-3 py-2 text-sm text-slate-400 transition hover:bg-slate-800 hover:text-white"
                                @click="logout"
                            >
                                Logout
                            </button>
                        </div>

                        <!-- USER SEARCH -->

                        <div class="relative mt-4">
                            <div
                                class="relative"
                            >
                                <span
                                    class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-500"
                                >
                                    🔎
                                </span>

                                <input
                                    v-model="searchQuery"
                                    type="search"
                                    placeholder="Search registered users..."
                                    autocomplete="off"
                                    class="w-full rounded-xl border border-slate-700 bg-slate-800 py-3 pl-10 pr-4 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-emerald-500"
                                    @input="searchUsers"
                                />
                            </div>

                            <!-- SEARCH RESULTS -->

                            <div
                                v-if="
                                    searchQuery.trim()
                                "
                                class="absolute left-0 right-0 top-full z-50 mt-2 overflow-hidden rounded-2xl border border-slate-700 bg-slate-900 shadow-2xl"
                            >
                                <div
                                    v-if="
                                        searchingUsers
                                    "
                                    class="px-4 py-5 text-center text-sm text-slate-500"
                                >
                                    Searching registered users...
                                </div>

                                <div
                                    v-else-if="
                                        searchedUsers.length
                                    "
                                    class="max-h-[60vh] overflow-y-auto"
                                >
                                    <div
                                        class="border-b border-slate-800 px-4 py-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500"
                                    >
                                        Registered users
                                    </div>

                                    <button
                                        v-for="searchedUser in searchedUsers"
                                        :key="
                                            searchedUser.id
                                        "
                                        type="button"
                                        class="flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-slate-800"
                                        @click="
                                            openUserConversation(
                                                searchedUser,
                                            )
                                        "
                                    >
                                        <div
                                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-emerald-500 font-bold text-slate-950"
                                        >
                                            {{
                                                searchedUser.name
                                                    ?.charAt(
                                                        0,
                                                    )
                                                    ?.toUpperCase()
                                            }}
                                        </div>

                                        <div
                                            class="min-w-0 flex-1"
                                        >
                                            <p
                                                class="truncate font-semibold text-white"
                                            >
                                                {{
                                                    searchedUser.name
                                                }}
                                            </p>

                                            <p
                                                class="truncate text-sm text-slate-500"
                                            >
                                                @{{ searchedUser.username }}
                                            </p>
                                        </div>

                                        <span
                                            class="text-xs text-emerald-400"
                                        >
                                            Chat
                                        </span>
                                    </button>
                                </div>

                                <div
                                    v-else
                                    class="px-4 py-6 text-center"
                                >
                                    <div
                                        class="text-2xl"
                                    >
                                        👤
                                    </div>

                                    <p
                                        class="mt-2 text-sm text-slate-400"
                                    >
                                        No registered users found.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- CONVERSATION LIST -->

                    <div
                        class="min-h-0 flex-1 overflow-y-auto overscroll-contain"
                    >
                        <div
                            v-if="
                                loadingConversations
                            "
                            class="p-6 text-center text-sm text-slate-500"
                        >
                            Loading conversations...
                        </div>

                        <div
                            v-else-if="
                                conversations.length ===
                                0
                            "
                            class="px-6 py-16 text-center"
                        >
                            <div
                                class="text-5xl"
                            >
                                💬
                            </div>

                            <h2
                                class="mt-4 font-semibold text-slate-300"
                            >
                                No conversations yet
                            </h2>

                            <p
                                class="mt-2 text-sm leading-6 text-slate-500"
                            >
                                Search for a registered user above to start chatting.
                            </p>
                        </div>

                        <button
                            v-for="conversation in conversations"
                            :key="conversation.id"
                            type="button"
                            class="flex w-full gap-3 border-b border-slate-800/80 px-4 py-3.5 text-left transition hover:bg-slate-800"
                            :class="{
                                'bg-slate-800':
                                    activeConversation?.id ===
                                    conversation.id,
                            }"
                            @click="
                                selectConversation(
                                    conversation,
                                )
                            "
                        >
                            <!-- AVATAR -->

                            <div
                                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-emerald-500 font-bold text-slate-950"
                            >
                                {{
                                    getUserInitial(
                                        getOtherUser(
                                            conversation,
                                        ),
                                    )
                                }}
                            </div>

                            <!-- CONVERSATION INFO -->

                            <div
                                class="min-w-0 flex-1"
                            >
                                <div
                                    class="flex items-center justify-between gap-2"
                                >
                                    <h2
                                        class="truncate font-semibold text-white"
                                    >
                                        {{
                                            getOtherUser(
                                                conversation,
                                            )?.name ??
                                            'Unknown user'
                                        }}
                                    </h2>

                                    <span
                                        v-if="
                                            conversation.latest_message
                                        "
                                        class="shrink-0 text-[11px] text-slate-500"
                                    >
                                        {{
                                            formatConversationTime(
                                                conversation
                                                    .latest_message
                                                    .created_at,
                                            )
                                        }}
                                    </span>
                                </div>

                                <div
                                    class="mt-1 flex items-center gap-2"
                                >
                                    <p
                                        class="min-w-0 flex-1 truncate text-sm text-slate-400"
                                    >
                                        {{
                                            getConversationPreview(
                                                conversation,
                                            )
                                        }}
                                    </p>

                                    <span
                                        v-if="
                                            conversation.unread_messages_count >
                                            0
                                        "
                                        class="flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-emerald-500 px-1.5 text-[11px] font-bold text-slate-950"
                                    >
                                        {{
                                            conversation.unread_messages_count
                                        }}
                                    </span>
                                </div>
                            </div>
                        </button>
                    </div>

                    <!-- CURRENT USER -->

                    <!-- MOBILE BOTTOM NAVIGATION -->
                    <div
                        class="shrink-0 border-t border-slate-800 bg-slate-900 px-2 pb-[max(0.5rem,env(safe-area-inset-bottom))] pt-2 md:hidden"
                    >
                        <div class="grid grid-cols-2 gap-1">
                            <button
                                type="button"
                                class="flex flex-col items-center justify-center gap-1 rounded-xl bg-slate-800 py-2 text-emerald-400"
                            >
                                <span class="text-xl leading-none">💬</span>
                                <span class="text-[11px] font-semibold">Chats</span>
                            </button>

                            <button
                                type="button"
                                class="flex flex-col items-center justify-center gap-1 rounded-xl py-2 text-slate-500 transition hover:bg-slate-800 hover:text-white"
                            >
                                <span class="text-xl leading-none">👤</span>
                                <span class="text-[11px] font-semibold">Profile</span>
                            </button>
                        </div>
                    </div>

                    <!-- DESKTOP ACCOUNT FOOTER -->
                    <div
                        class="hidden shrink-0 border-t border-slate-800 bg-slate-900 p-4 md:block"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-700 font-bold"
                            >
                                {{
                                    getUserInitial(
                                        user,
                                    )
                                }}
                            </div>

                            <div class="min-w-0">
                                <p class="truncate font-semibold text-white">
                                    {{ user.name }}
                                </p>

                                <p class="truncate text-xs text-slate-500">
                                    @{{ user.username }}
                                </p>
                            </div>
                        </div>
                    </div>
                </aside>

                <!-- =================================================
                     CHAT PANEL
                     MOBILE: shown only after selecting conversation
                     TABLET/DESKTOP: always occupies right side
                ================================================== -->

                <section
                    class="min-w-0 flex-1 flex-col bg-slate-950"
                    :class="
                        activeConversation
                            ? 'flex'
                            : 'hidden md:flex'
                    "
                >
                    <!-- =================================================
                         CHAT HEADER
                    ================================================== -->

                    <header
                        v-if="activeConversation"
                        class="flex h-16 shrink-0 items-center gap-3 border-b border-slate-800 bg-slate-900 px-3 sm:px-5"
                    >
                        <!-- MOBILE BACK BUTTON -->

                        <button
                            type="button"
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-2xl text-slate-300 transition hover:bg-slate-800 hover:text-white md:hidden"
                            aria-label="Back to conversations"
                            @click="
                                closeMobileChat
                            "
                        >
                            ←
                        </button>

                        <!-- AVATAR -->

                        <div
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-emerald-500 font-bold text-slate-950"
                        >
                            {{
                                getUserInitial(
                                    getOtherUser(
                                        activeConversation,
                                    ),
                                )
                            }}
                        </div>

                        <!-- USER DETAILS -->

                        <div
                            class="min-w-0 flex-1"
                        >
                            <h2
                                class="truncate font-bold text-white"
                            >
                                {{
                                    getOtherUser(
                                        activeConversation,
                                    )?.name ??
                                    'Unknown user'
                                }}
                            </h2>

                            <p
                                class="truncate text-sm text-slate-500"
                            >
                                @{{ getOtherUser(activeConversation)?.username }}
                            </p>
                        </div>
                    </header>

                    <!-- =================================================
                         EMPTY DESKTOP/TABLET STATE
                    ================================================== -->

                    <div
                        v-if="
                            !activeConversation
                        "
                        class="hidden flex-1 items-center justify-center md:flex"
                    >
                        <div
                            class="px-6 text-center"
                        >
                            <div
                                class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-slate-900 text-4xl"
                            >
                                💬
                            </div>

                            <h2
                                class="mt-5 text-xl font-bold text-white"
                            >
                                Select a conversation
                            </h2>

                            <p
                                class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500"
                            >
                                Choose a chat from the sidebar or search for a registered user to start messaging.
                            </p>
                        </div>
                    </div>

                    <template
                        v-else
                    >
                        <!-- =================================================
                             MESSAGES
                        ================================================== -->

                        <div
                            class="min-h-0 flex-1 overflow-y-auto px-3 py-5 sm:px-6 sm:py-6"
                        >
                            <div
                                class="mx-auto flex w-full max-w-4xl flex-col gap-4"
                            >
                                <!-- LOADING -->

                                <div
                                    v-if="
                                        loadingMessages
                                    "
                                    class="flex min-h-[50vh] items-center justify-center text-sm text-slate-500"
                                >
                                    Loading messages...
                                </div>

                                <!-- EMPTY -->

                                <div
                                    v-else-if="
                                        messages.length ===
                                        0
                                    "
                                    class="flex min-h-[50vh] items-center justify-center"
                                >
                                    <div
                                        class="text-center"
                                    >
                                        <div
                                            class="text-5xl"
                                        >
                                            👋
                                        </div>

                                        <h2
                                            class="mt-4 font-semibold text-slate-300"
                                        >
                                            No messages yet
                                        </h2>

                                        <p
                                            class="mt-2 text-sm text-slate-500"
                                        >
                                            Send a message to start the conversation.
                                        </p>
                                    </div>
                                </div>

                                <!-- MESSAGE LIST -->

                                <template
                                    v-else
                                >
                                    <div
                                        v-for="message in messages"
                                        :key="message.id"
                                        class="group flex"
                                        :class="
                                            isMyMessage(
                                                message,
                                            )
                                                ? 'justify-end'
                                                : 'justify-start'
                                        "
                                    >
                                        <div
                                            class="relative max-w-[88%] sm:max-w-[75%]"
                                        >
                                            <!-- MESSAGE ACTIONS -->

                                            <div
                                                class="absolute -top-9 z-10 hidden items-center gap-1 rounded-xl border border-slate-700 bg-slate-900 p-1 shadow-xl group-hover:flex"
                                                :class="
                                                    isMyMessage(
                                                        message,
                                                    )
                                                        ? 'right-0'
                                                        : 'left-0'
                                                "
                                            >
                                                <!-- HEART -->

                                                <button
                                                    type="button"
                                                    class="rounded-lg px-2 py-1.5 text-sm transition hover:bg-slate-800"
                                                    :class="{
                                                        'bg-slate-800':
                                                            hasReacted(
                                                                message,
                                                                '❤️',
                                                            ),
                                                    }"
                                                    title="Like"
                                                    :disabled="
                                                        reactingMessageId ===
                                                        message.id
                                                    "
                                                    @click="
                                                        toggleReaction(
                                                            message,
                                                            '❤️',
                                                        )
                                                    "
                                                >
                                                    {{
                                                        hasReacted(
                                                            message,
                                                            '❤️',
                                                        )
                                                            ? '❤️'
                                                            : '🤍'
                                                    }}
                                                </button>

                                                <!-- OTHER REACTIONS -->

                                                <div
                                                    class="hidden items-center gap-1 lg:flex"
                                                >
                                                    <button
                                                        v-for="reactionType in reactionTypes.filter(
                                                            (type) =>
                                                                type !==
                                                                '❤️',
                                                        )"
                                                        :key="
                                                            reactionType
                                                        "
                                                        type="button"
                                                        class="rounded-lg px-2 py-1.5 text-sm transition hover:bg-slate-800"
                                                        :class="{
                                                            'bg-slate-800':
                                                                hasReacted(
                                                                    message,
                                                                    reactionType,
                                                                ),
                                                        }"
                                                        :disabled="
                                                            reactingMessageId ===
                                                            message.id
                                                        "
                                                        @click="
                                                            toggleReaction(
                                                                message,
                                                                reactionType,
                                                            )
                                                        "
                                                    >
                                                        {{
                                                            reactionType
                                                        }}
                                                    </button>
                                                </div>

                                                <!-- EDIT -->

                                                <button
                                                    v-if="
                                                        isMyMessage(
                                                            message,
                                                        )
                                                    "
                                                    type="button"
                                                    class="rounded-lg px-2 py-1.5 text-xs text-slate-300 hover:bg-slate-800"
                                                    @click="
                                                        startEditingMessage(
                                                            message,
                                                        )
                                                    "
                                                >
                                                    Edit
                                                </button>

                                                <!-- DELETE -->

                                                <button
                                                    v-if="
                                                        isMyMessage(
                                                            message,
                                                        )
                                                    "
                                                    type="button"
                                                    class="rounded-lg px-2 py-1.5 text-xs text-red-400 hover:bg-slate-800"
                                                    :disabled="
                                                        deletingMessageId ===
                                                        message.id
                                                    "
                                                    @click="
                                                        deleteMessage(
                                                            message,
                                                        )
                                                    "
                                                >
                                                    {{
                                                        deletingMessageId ===
                                                        message.id
                                                            ? '...'
                                                            : 'Delete'
                                                    }}
                                                </button>
                                            </div>

                                            <!-- MESSAGE BUBBLE -->

                                            <div
                                                class="rounded-2xl px-4 py-3 shadow-sm"
                                                :class="
                                                    isMyMessage(
                                                        message,
                                                    )
                                                        ? 'rounded-br-md bg-emerald-500 text-slate-950'
                                                        : 'rounded-bl-md bg-slate-800 text-white'
                                                "
                                            >
                                                <p
                                                    class="whitespace-pre-wrap break-words text-sm leading-6 sm:text-[15px]"
                                                >
                                                    {{
                                                        message.body
                                                    }}
                                                </p>

                                                <div
                                                    class="mt-1 flex items-center justify-end"
                                                >
                                                    <span
                                                        class="text-[10px]"
                                                        :class="
                                                            isMyMessage(
                                                                message,
                                                            )
                                                                ? 'text-slate-700'
                                                                : 'text-slate-500'
                                                        "
                                                    >
                                                        {{
                                                            formatMessageTime(
                                                                message.created_at,
                                                            )
                                                        }}
                                                    </span>
                                                </div>
                                            </div>

                                            <!-- REACTIONS -->

                                            <div
                                                v-if="
                                                    getMessageReactions(
                                                        message,
                                                    ).length
                                                "
                                                class="absolute -bottom-4 left-3 flex flex-wrap items-center gap-1"
                                            >
                                                <template
                                                    v-for="reactionType in reactionTypes"
                                                    :key="`${message.id}-${reactionType}`"
                                                >
                                                    <button
                                                        v-if="
                                                            getReactionCount(
                                                                message,
                                                                reactionType,
                                                            ) >
                                                            0
                                                        "
                                                        type="button"
                                                        class="rounded-full border border-slate-700 bg-slate-900 px-2 py-0.5 text-xs shadow-md transition hover:border-emerald-500"
                                                        :class="{
                                                            'border-emerald-500':
                                                                hasReacted(
                                                                    message,
                                                                    reactionType,
                                                                ),
                                                        }"
                                                        @click="
                                                            toggleReaction(
                                                                message,
                                                                reactionType,
                                                            )
                                                        "
                                                    >
                                                        <span>
                                                            {{
                                                                reactionType
                                                            }}
                                                        </span>

                                                        <span
                                                            v-if="
                                                                getReactionCount(
                                                                    message,
                                                                    reactionType,
                                                                ) >
                                                                1
                                                            "
                                                            class="ml-1 text-slate-300"
                                                        >
                                                            {{
                                                                getReactionCount(
                                                                    message,
                                                                    reactionType,
                                                                )
                                                            }}
                                                        </span>
                                                    </button>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- =================================================
                             EDIT MESSAGE
                        ================================================== -->

                        <div
                            v-if="
                                editingMessage
                            "
                            class="shrink-0 border-t border-slate-800 bg-slate-900 px-3 py-3 sm:px-5"
                        >
                            <div
                                class="mx-auto max-w-4xl"
                            >
                                <div
                                    class="mb-2 flex items-center justify-between"
                                >
                                    <p
                                        class="text-xs font-semibold text-emerald-400"
                                    >
                                        Editing message
                                    </p>

                                    <button
                                        type="button"
                                        class="text-xs text-slate-500 hover:text-white"
                                        @click="
                                            cancelEditing
                                        "
                                    >
                                        Cancel
                                    </button>
                                </div>

                                <div
                                    class="flex gap-2 sm:gap-3"
                                >
                                    <input
                                        v-model="
                                            editingBody
                                        "
                                        type="text"
                                        class="min-w-0 flex-1 rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm text-white outline-none focus:border-emerald-500"
                                        @keyup.enter="
                                            updateMessage
                                        "
                                    />

                                    <button
                                        type="button"
                                        class="rounded-xl bg-emerald-500 px-4 py-3 text-sm font-bold text-slate-950 hover:bg-emerald-400 sm:px-5"
                                        @click="
                                            updateMessage
                                        "
                                    >
                                        Save
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- =================================================
                             MESSAGE COMPOSER
                        ================================================== -->

                        <form
                            v-else
                            class="relative shrink-0 border-t border-slate-800 bg-slate-900 p-3 sm:p-4"
                            @submit.prevent="
                                sendMessage
                            "
                        >
                            <!-- EMOJI PICKER -->

                            <div
                                v-if="
                                    showEmojiPicker
                                "
                                class="absolute bottom-full left-3 z-50 mb-2 grid max-h-64 w-[calc(100%-1.5rem)] max-w-sm grid-cols-7 gap-1 overflow-y-auto rounded-2xl border border-slate-700 bg-slate-900 p-3 shadow-2xl sm:left-4 sm:grid-cols-8"
                            >
                                <button
                                    v-for="emoji in emojis"
                                    :key="emoji"
                                    type="button"
                                    class="rounded-lg p-2 text-xl transition hover:bg-slate-800"
                                    @click="
                                        addEmoji(
                                            emoji,
                                        )
                                    "
                                >
                                    {{ emoji }}
                                </button>
                            </div>

                            <div
                                class="mx-auto flex max-w-4xl items-center gap-2 sm:gap-3"
                            >
                                <!-- EMOJI -->

                                <button
                                    type="button"
                                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-slate-700 bg-slate-800 text-xl transition hover:bg-slate-700"
                                    title="Emoji"
                                    @click="
                                        toggleEmojiPicker
                                    "
                                >
                                    😊
                                </button>

                                <!-- MESSAGE INPUT -->

                                <input
                                    v-model="
                                        messageBody
                                    "
                                    type="text"
                                    placeholder="Type a message..."
                                    autocomplete="off"
                                    class="min-w-0 flex-1 rounded-xl border border-slate-700 bg-slate-800 px-3 py-3 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-emerald-500 sm:px-4"
                                />

                                <!-- SEND -->

                                <button
                                    type="submit"
                                    :disabled="
                                        sendingMessage ||
                                        !messageBody.trim()
                                    "
                                    class="flex h-11 shrink-0 items-center justify-center rounded-xl bg-emerald-500 px-4 font-bold text-slate-950 transition hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-50 sm:px-5"
                                >
                                    <span
                                        class="hidden sm:inline"
                                    >
                                        {{
                                            sendingMessage
                                                ? '...'
                                                : 'Send'
                                        }}
                                    </span>

                                    <span
                                        class="text-lg sm:hidden"
                                    >
                                        {{
                                            sendingMessage
                                                ? '...'
                                                : '➤'
                                        }}
                                    </span>
                                </button>
                            </div>
                        </form>
                    </template>
                </section>
            </div>
        </section>
    </main>
</template>
