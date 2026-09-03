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
const otherUserTyping = ref(false);
const otherUserOnline = ref(false);
const messageScrollContainer = ref(null);
const loadingOlderMessages = ref(false);
const hasOlderMessages = ref(false);
const nextMessagePage = ref(2);

const messageBody = ref('');
const messageSearchQuery = ref('');
const messageFileInput = ref(null);
const selectedMessageFile = ref(null);
const showProfile = ref(false);
const profileForm = ref({
    name: '',
    username: '',
    email: '',
    password: '',
    password_confirmation: '',
});
const savingProfile = ref(false);
const previewImage = ref(null);

const searchQuery = ref('');
const searchedUsers = ref([]);
const searchingUsers = ref(false);
const showGroupCreator = ref(false);
const groupName = ref('');
const groupMembers = ref([]);
const friends = ref([]);
const loadingFriends = ref(false);
const showMemberManager = ref(false);
const additionalMembers = ref([]);
const showGroupMembers = ref(false);

const loadingConversations = ref(false);
const loadingMessages = ref(false);
const sendingMessage = ref(false);
const incomingAlert = ref(null);
const notificationsEnabled = ref(
    typeof Notification !== 'undefined' &&
        Notification.permission === 'granted',
);
    const knownLatestMessageIds = new Map();

const editingMessage = ref(null);
const editingBody = ref('');

const deletingMessageId = ref(null);

const reactingMessageId = ref(null);
const activeReactionMessageId = ref(null);

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

const isGroupConversation = (conversation) => {
    return Number(conversation?.users?.length ?? 0) > 2 || Boolean(conversation?.name);
};

const getConversationTitle = (conversation) => {
    return conversation?.name ?? getOtherUser(conversation)?.name ?? 'Unknown user';
};

const openGroupCreator = async () => {
    showGroupCreator.value = true;
    groupName.value = '';
    groupMembers.value = [];
    searchQuery.value = '';
    searchedUsers.value = [];

    loadingFriends.value = true;

    try {
        const response = await fetch('/api/friends', {
            headers: apiHeaders(),
        });
        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Failed to load friends.');
        }

        friends.value = data.data ?? [];
    } catch (err) {
        error.value = err.message || 'Failed to load friends.';
        friends.value = [];
    } finally {
        loadingFriends.value = false;
    }
};

const closeGroupCreator = () => {
    showGroupCreator.value = false;
    groupName.value = '';
    groupMembers.value = [];
    searchQuery.value = '';
    searchedUsers.value = [];
    friends.value = [];
};

const toggleGroupMember = (selectedUser) => {
    const memberIndex = groupMembers.value.findIndex(
        (member) => Number(member.id) === Number(selectedUser.id),
    );

    if (memberIndex === -1) {
        groupMembers.value.push(selectedUser);
    } else {
        groupMembers.value.splice(memberIndex, 1);
    }
};

const openMemberManager = async () => {
    additionalMembers.value = [];
    showMemberManager.value = true;

    if (!friends.value.length) {
        await openGroupCreator();
        showGroupCreator.value = false;
    }
};

const closeMemberManager = () => {
    showMemberManager.value = false;
    additionalMembers.value = [];
};

const openGroupMembers = () => {
    showGroupMembers.value = true;
};

const closeGroupMembers = () => {
    showGroupMembers.value = false;
};

const toggleAdditionalMember = (selectedUser) => {
    const memberIndex = additionalMembers.value.findIndex(
        (member) => Number(member.id) === Number(selectedUser.id),
    );

    if (memberIndex === -1) {
        additionalMembers.value.push(selectedUser);
    } else {
        additionalMembers.value.splice(memberIndex, 1);
    }
};

const isConversationMember = (selectedUser) => {
    return activeConversation.value?.users?.some(
        (member) => Number(member.id) === Number(selectedUser.id),
    );
};

const addMembersToGroup = async () => {
    if (!activeConversation.value || !additionalMembers.value.length) {
        return;
    }

    try {
        const response = await fetch(
            `/api/conversations/${activeConversation.value.id}/members`,
            {
                method: 'PATCH',
                headers: {
                    ...apiHeaders(),
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_ids: additionalMembers.value.map((member) => member.id),
                }),
            },
        );
        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Failed to add group members.');
        }

        activeConversation.value = data.conversation;
        const conversationIndex = conversations.value.findIndex(
            (conversation) => Number(conversation.id) === Number(data.conversation.id),
        );
        if (conversationIndex !== -1) {
            conversations.value[conversationIndex] = data.conversation;
        }
        closeMemberManager();
    } catch (err) {
        error.value = err.message || 'Failed to add group members.';
    }
};

const isGroupMember = (selectedUser) => {
    return groupMembers.value.some(
        (member) => Number(member.id) === Number(selectedUser.id),
    );
};

const createGroup = async () => {
    if (!groupName.value.trim() || groupMembers.value.length === 0) {
        error.value = 'Add at least one member and a group name.';
        return;
    }

    loadingConversations.value = true;

    try {
        const response = await fetch('/api/conversations', {
            method: 'POST',
            headers: {
                ...apiHeaders(),
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                name: groupName.value.trim(),
                user_ids: groupMembers.value.map((member) => member.id),
            }),
        });
        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Failed to create group.');
        }

        conversations.value.unshift(data.conversation);
        closeGroupCreator();
        await selectConversation(data.conversation);
    } catch (err) {
        error.value = err.message || 'Failed to create group.';
    } finally {
        loadingConversations.value = false;
    }
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

const openProfile = () => {
    profileForm.value = {
        name: user.value?.name ?? '',
        username: user.value?.username ?? '',
        email: user.value?.email ?? '',
        password: '',
        password_confirmation: '',
    };
    showProfile.value = true;
};

const closeProfile = () => {
    showProfile.value = false;
};

const updateProfile = async () => {
    savingProfile.value = true;
    resetMessages();

    try {
        const response = await fetch('/api/user', {
            method: 'PATCH',
            headers: {
                ...apiHeaders(),
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(profileForm.value),
        });
        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Failed to update profile.');
        }

        user.value = data.user;
        showProfile.value = false;
        success.value = 'Profile updated successfully.';
    } catch (err) {
        error.value = err.message || 'Failed to update profile.';
    } finally {
        savingProfile.value = false;
    }
};

const enableNotifications = async () => {
    if (!('Notification' in window)) {
        error.value = 'Browser notifications are not supported.';
        return;
    }

    const permission = await Notification.requestPermission();
    notificationsEnabled.value = permission === 'granted';

    if (permission === 'denied') {
        error.value = 'Browser notifications are blocked.';
    }
};

const ringNotification = () => {
    try {
        const audioContext = new AudioContext();
        const oscillator = audioContext.createOscillator();
        const gain = audioContext.createGain();

        oscillator.frequency.setValueAtTime(880, audioContext.currentTime);
        oscillator.frequency.setValueAtTime(660, audioContext.currentTime + 0.18);
        gain.gain.setValueAtTime(0.25, audioContext.currentTime);
        gain.gain.exponentialRampToValueAtTime(
            0.001,
            audioContext.currentTime + 0.45,
        );

        oscillator.connect(gain);
        gain.connect(audioContext.destination);
        oscillator.start();
        oscillator.stop(audioContext.currentTime + 0.45);
    } catch {
        // Browser autoplay policies may block the alert tone.
    }
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
        window.setEchoToken?.(data.token);

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
        window.setEchoToken?.(data.token);

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
        window.setEchoToken?.(null);
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

        conversations.value.forEach((conversation) => {
            const latestMessage = conversation.latest_message;

            knownLatestMessageIds.set(
                Number(conversation.id),
                latestMessage?.id ? Number(latestMessage.id) : 0,
            );
        });
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
    messageSearchQuery.value = '';
    activeReactionMessageId.value = null;

    showEmojiPicker.value = false;

    cancelEditing();

    subscribeToConversation(conversation);

    await loadMessages(conversation);

    await markConversationAsRead(conversation);
    startMessageRefresh();
};

const closeMobileChat = () => {
    activeConversation.value = null;
    messages.value = [];
    activeReactionMessageId.value = null;
    hasOlderMessages.value = false;
    nextMessagePage.value = 2;

    showEmojiPicker.value = false;

    cancelEditing();

    leaveCurrentChannel();
};

const loadMessages = async (conversation) => {
    loadingMessages.value = true;
    messages.value = [];
    hasOlderMessages.value = false;
    nextMessagePage.value = 2;

    try {
        const response = await fetch(
            `/api/conversations/${conversation.id}/messages?page=1&search=${encodeURIComponent(messageSearchQuery.value.trim())}`,
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

        messages.value = sortMessagesByTime(
            messages.value,
        );

        hasOlderMessages.value =
            Number(data.data?.current_page ?? 1) <
            Number(data.data?.last_page ?? 1);
    } catch (err) {
        error.value =
            err.message || 'Failed to load messages.';
    } finally {
        loadingMessages.value = false;
    }
};

let messageRefreshInterval = null;
let incomingAlertTimeout = null;

const showIncomingAlert = (message) => {
    if (Number(message.sender_id) === Number(user.value?.id)) {
        return;
    }

    incomingAlert.value = {
        sender: message.sender?.name ?? 'New message',
        body: message.body || 'Sent an attachment',
    };

    clearTimeout(incomingAlertTimeout);
    incomingAlertTimeout = setTimeout(() => {
        incomingAlert.value = null;
    }, 5000);

    ringNotification();

    if (
        notificationsEnabled.value &&
        document.hidden
    ) {
        const notification = new Notification(
            message.sender?.name ?? 'New message',
            {
                body: message.body || 'Sent an attachment',
                tag: `conversation-${message.conversation_id}`,
            },
        );

        notification.onclick = () => window.focus();
    }
};

const refreshConversationNotifications = async () => {
    if (!token.value) {
        return;
    }

    try {
        const response = await fetch('/api/conversations', {
            headers: apiHeaders(),
        });
        const data = await response.json();

        if (!response.ok) {
            return;
        }

        const latestConversations = data.data?.data ?? [];

        latestConversations.forEach((conversation) => {
            const latestMessage = conversation.latest_message;
            const conversationId = Number(conversation.id);
            const latestMessageId = Number(latestMessage?.id);
            const knownMessageId = knownLatestMessageIds.get(conversationId);

            if (
                latestMessage?.id &&
                knownMessageId !== undefined &&
                latestMessageId !== knownMessageId
            ) {
                showIncomingAlert(latestMessage);
            }

            if (latestMessage?.id) {
                knownLatestMessageIds.set(conversationId, latestMessageId);
            }
        });

        conversations.value = latestConversations;
    } catch {
        // The next refresh will retry when the API is available again.
    }
};

let conversationRefreshInterval = null;

const startConversationRefresh = () => {
    clearInterval(conversationRefreshInterval);
    conversationRefreshInterval = setInterval(
        refreshConversationNotifications,
        3000,
    );
};

const refreshMessages = async () => {
    if (!activeConversation.value || loadingMessages.value) {
        return;
    }

    try {
        const response = await fetch(
            `/api/conversations/${activeConversation.value.id}/messages?page=1&search=${encodeURIComponent(messageSearchQuery.value.trim())}`,
            {
                headers: apiHeaders(),
            },
        );

        const data = await response.json();

        if (!response.ok) {
            return;
        }

        const latestMessages = (data.data?.data ?? []).map((message) => ({
            ...message,
            reactions: message.reactions ?? [],
        }));
        const existingIds = new Set(
            messages.value.map((message) => Number(message.id)),
        );

        latestMessages
            .filter((message) => !existingIds.has(Number(message.id)))
            .forEach(showIncomingAlert);

        const messagesById = new Map(
            [...messages.value, ...latestMessages].map((message) => [
                Number(message.id),
                message,
            ]),
        );

        messages.value = sortMessagesByTime([...messagesById.values()]);
    } catch {
        // Realtime delivery remains available when the fallback request fails.
    }
};

const startMessageRefresh = () => {
    clearInterval(messageRefreshInterval);
    messageRefreshInterval = setInterval(refreshMessages, 3000);
};

const loadOlderMessages = async () => {
    if (
        !activeConversation.value ||
        loadingOlderMessages.value ||
        !hasOlderMessages.value
    ) {
        return;
    }

    const scrollContainer = messageScrollContainer.value;
    const previousHeight = scrollContainer?.scrollHeight ?? 0;
    const previousTop = scrollContainer?.scrollTop ?? 0;

    loadingOlderMessages.value = true;

    try {
        const response = await fetch(
            `/api/conversations/${activeConversation.value.id}/messages?page=${nextMessagePage.value}&search=${encodeURIComponent(messageSearchQuery.value.trim())}`,
            {
                headers: apiHeaders(),
            },
        );

        const data = await response.json();

        if (!response.ok) {
            throw new Error(
                data.message || 'Failed to load older messages.',
            );
        }

        const olderMessages = (
            data.data?.data ?? []
        ).map((message) => ({
            ...message,
            reactions: message.reactions ?? [],
        }));

        messages.value = sortMessagesByTime([
            ...olderMessages,
            ...messages.value,
        ]);

        hasOlderMessages.value =
            Number(data.data?.current_page ?? nextMessagePage.value) <
            Number(data.data?.last_page ?? nextMessagePage.value);
        nextMessagePage.value += 1;

        await new Promise((resolve) => requestAnimationFrame(resolve));

        if (scrollContainer) {
            scrollContainer.scrollTop =
                scrollContainer.scrollHeight - previousHeight + previousTop;
        }
    } catch (err) {
        error.value =
            err.message || 'Failed to load older messages.';
    } finally {
        loadingOlderMessages.value = false;
    }
};

let messageSearchTimeout = null;

const searchMessages = () => {
    clearTimeout(messageSearchTimeout);

    messageSearchTimeout = setTimeout(() => {
        if (activeConversation.value) {
            loadMessages(activeConversation.value);
        }
    }, 300);
};

const handleMessageScroll = (event) => {
    if (event.currentTarget.scrollTop <= 80) {
        loadOlderMessages();
    }
};

const markConversationAsRead = async (conversation) => {
    try {
        const response = await fetch(
            `/api/conversations/${conversation.id}/messages/read`,
            {
                method: 'PATCH',
                headers: apiHeaders(),
            },
        );

        const data = await response.json();

        if (!response.ok) {
            throw new Error(
                data.message || 'Failed to mark messages as read.',
            );
        }

        const conversationIndex = conversations.value.findIndex(
            (item) => Number(item.id) === Number(conversation.id),
        );

        if (conversationIndex !== -1) {
            conversations.value[conversationIndex].unread_messages_count = 0;
        }
    } catch (err) {
        error.value =
            err.message || 'Failed to mark messages as read.';
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

const addFriend = async (selectedUser) => {
    try {
        const response = await fetch(`/api/friends/${selectedUser.id}`, {
            method: 'POST',
            headers: apiHeaders(),
        });
        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Failed to add friend.');
        }

        if (!friends.value.some((friend) => Number(friend.id) === Number(data.friend.id))) {
            friends.value.push(data.friend);
        }
        success.value = `${data.friend.name} added to your friends.`;
    } catch (err) {
        error.value = err.message || 'Failed to add friend.';
    }
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
        (!body && !selectedMessageFile.value) ||
        !activeConversation.value ||
        sendingMessage.value
    ) {
        return;
    }

    sendingMessage.value = true;

    resetMessages();

    try {
        const formData = new FormData();

        if (body) {
            formData.append('body', body);
        }

        if (selectedMessageFile.value) {
            formData.append('attachment', selectedMessageFile.value);
        }

        const response = await fetch(
            `/api/conversations/${activeConversation.value.id}/messages`,
            {
                method: 'POST',
                headers: apiHeaders(),
                body: formData,
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

            messages.value = sortMessagesByTime(
                messages.value,
            );
        }

        messageBody.value = '';
        selectedMessageFile.value = null;
        if (messageFileInput.value) {
            messageFileInput.value.value = '';
        }
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

const selectMessageFile = (event) => {
    selectedMessageFile.value = event.target.files?.[0] ?? null;
};

const clearSelectedMessageFile = () => {
    selectedMessageFile.value = null;

    if (messageFileInput.value) {
        messageFileInput.value.value = '';
    }
};

const openImagePreview = (message) => {
    if (!message?.attachment_url) {
        return;
    }

    previewImage.value = {
        url: message.attachment_url,
        name: message.attachment_name ?? 'Image preview',
    };
};

const closeImagePreview = () => {
    previewImage.value = null;
};

const handlePreviewKeydown = (event) => {
    if (event.key === 'Escape') {
        closeImagePreview();
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

            messages.value = sortMessagesByTime(
                messages.value,
            );
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
    );

    try {
        const response = await fetch(
            `/api/conversations/${activeConversation.value.id}/messages/${message.id}/reactions`,
            {
                method: alreadyReacted ? 'DELETE' : 'POST',
                headers: {
                    ...apiHeaders(),
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ type }),
            },
        );

        const data = await response.json();

        if (!response.ok) {
            throw new Error(
                data.message || 'Failed to update reaction.',
            );
        }

        if (alreadyReacted) {
            removeLocalReaction(message, {
                user_id: user.value.id,
                type,
            });
        } else if (data.data) {
            addLocalReaction(message, data.data);
        }
    } catch (err) {
        error.value =
            err.message ||
            'Failed to update reaction.';
    } finally {
        reactingMessageId.value = null;
        activeReactionMessageId.value = null;
    }
};

const toggleReactionPicker = (message) => {
    activeReactionMessageId.value =
        activeReactionMessageId.value === message.id
            ? null
            : message.id;
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

const sortMessagesByTime = (list = []) => {
    return [...list].sort((a, b) => {
        const left = a?.created_at
            ? new Date(a.created_at).getTime()
            : 0;
        const right = b?.created_at
            ? new Date(b.created_at).getTime()
            : 0;

        return left - right;
    });
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
    const latestMessage =
        conversation?.latest_message ??
        conversation?.last_message;

    if (latestMessage?.body) {
        return latestMessage.body;
    }

    if (latestMessage?.attachment_mime?.startsWith('image/')) {
        return 'Image';
    }

    if (latestMessage?.attachment_mime?.startsWith('video/')) {
        return 'Video';
    }

    if (latestMessage?.attachment_url || latestMessage?.attachment_path) {
        return 'Attachment';
    }

    return 'No messages yet';
};

let searchTimeout = null;
let echoChannel = null;
let echoPrivateChannel = null;
let typingTimeout = null;
let typingStopTimeout = null;

const subscribeToConversation = (
    conversation,
) => {
    if (!window.Echo || !conversation) {
        return;
    }

    leaveCurrentChannel();

    echoChannel = conversation.id;

    echoPrivateChannel = window.Echo.join(
        `conversation.${conversation.id}`,
    );

    echoPrivateChannel
        .here((participants) => {
            const otherUser = getOtherUser(conversation);

            otherUserOnline.value = participants.some(
                (participant) =>
                    Number(participant.id) === Number(otherUser?.id),
            );
        })
        .joining((participant) => {
            const otherUser = getOtherUser(conversation);

            if (Number(participant.id) === Number(otherUser?.id)) {
                otherUserOnline.value = true;
            }
        })
        .leaving((participant) => {
            const otherUser = getOtherUser(conversation);

            if (Number(participant.id) === Number(otherUser?.id)) {
                otherUserOnline.value = false;
            }
        })
        .listen(
            '.message.sent',
            (message) => {
                knownLatestMessageIds.set(
                    Number(conversation.id),
                    Number(message.id),
                );

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

                    messages.value = sortMessagesByTime(
                        messages.value,
                    );
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

                showIncomingAlert(message);
            },
        )
        .listenForWhisper('typing', (payload) => {
            if (Number(payload?.user_id) === Number(user.value?.id)) {
                return;
            }

            otherUserTyping.value = Boolean(payload?.typing);

            clearTimeout(typingTimeout);

            if (payload?.typing) {
                typingTimeout = setTimeout(() => {
                    otherUserTyping.value = false;
                }, 2000);
            }
        })
        .listen('.messages.read', (payload) => {
            if (Number(payload?.reader_id) === Number(user.value?.id)) {
                return;
            }

            messages.value.forEach((message) => {
                if (Number(message.sender_id) === Number(user.value?.id)) {
                    message.read_at = payload.read_at;
                }
            });
        })
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

const handleTyping = () => {
    if (!echoPrivateChannel || !user.value) {
        return;
    }

    echoPrivateChannel.whisper('typing', {
        user_id: user.value.id,
        typing: true,
    });

    clearTimeout(typingStopTimeout);

    typingStopTimeout = setTimeout(() => {
        echoPrivateChannel?.whisper('typing', {
            user_id: user.value.id,
            typing: false,
        });
    }, 1000);
};

const leaveCurrentChannel = () => {
    clearInterval(messageRefreshInterval);
    clearTimeout(incomingAlertTimeout);
    incomingAlert.value = null;
    clearTimeout(typingTimeout);
    clearTimeout(typingStopTimeout);
    otherUserTyping.value = false;
    otherUserOnline.value = false;
    echoPrivateChannel = null;

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
    window.addEventListener('keydown', handlePreviewKeydown);
    await loadUser();
    startConversationRefresh();
});

onUnmounted(() => {
    window.removeEventListener('keydown', handlePreviewKeydown);
    clearInterval(conversationRefreshInterval);
    clearTimeout(searchTimeout);
    clearTimeout(messageSearchTimeout);
    clearTimeout(typingTimeout);
    clearTimeout(typingStopTimeout);
    leaveCurrentChannel();
});
</script>

<template>
    <main
        class="h-[100svh] min-h-[100dvh] overflow-hidden bg-slate-950 text-white"
    >
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
                                class="shrink-0 rounded-xl px-3 py-2 text-sm text-emerald-400 transition hover:bg-slate-800"
                                @click="openGroupCreator"
                            >
                                + Group
                            </button>

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
                                            showGroupCreator
                                                ? toggleGroupMember(searchedUser)
                                                : openUserConversation(searchedUser)
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
                                            class="text-xs"
                                            :class="
                                                showGroupCreator && isGroupMember(searchedUser)
                                                    ? 'text-emerald-400'
                                                    : 'text-slate-500'
                                            "
                                            @click.stop="addFriend(searchedUser)"
                                        >
                                            Add friend
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
                                            getConversationTitle(
                                                conversation,
                                            )
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
                                @click="openProfile"
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

                        <button
                            type="button"
                            class="mt-3 w-full rounded-lg border border-slate-700 px-3 py-2 text-left text-xs font-semibold text-slate-400 transition hover:bg-slate-800 hover:text-white"
                            @click="openProfile"
                        >
                            Edit profile
                        </button>

                        <button
                            v-if="!notificationsEnabled"
                            type="button"
                            class="mt-2 w-full rounded-lg border border-slate-700 px-3 py-2 text-xs font-semibold text-slate-400 transition hover:bg-slate-800 hover:text-white"
                            @click="enableNotifications"
                        >
                            Enable notifications
                        </button>
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
                                    isGroupConversation(activeConversation)
                                        ? activeConversation.name?.charAt(0)?.toUpperCase() ?? 'G'
                                        : getUserInitial(getOtherUser(activeConversation))
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
                                    getConversationTitle(
                                        activeConversation,
                                    )
                                }}
                            </h2>

                            <p
                                v-if="!isGroupConversation(activeConversation)"
                                class="truncate text-sm text-slate-500"
                            >
                                @{{ getOtherUser(activeConversation)?.username }}
                            </p>
                            <button
                                v-else
                                type="button"
                                class="text-xs text-slate-500 hover:text-emerald-400"
                                @click="openGroupMembers"
                            >
                                {{ activeConversation.users?.length ?? 0 }} members
                            </button>

                            <div
                                class="mt-1 flex items-center gap-1.5 text-xs"
                                :class="
                                    otherUserOnline
                                        ? 'text-emerald-400'
                                        : 'text-slate-500'
                                "
                            >
                                <span
                                    class="h-1.5 w-1.5 rounded-full"
                                    :class="
                                        otherUserOnline
                                            ? 'bg-emerald-400'
                                            : 'bg-slate-600'
                                    "
                                ></span>

                                {{
                                    otherUserOnline
                                        ? 'Online'
                                        : 'Offline'
                                }}
                            </div>
                        </div>

                        <button
                            v-if="isGroupConversation(activeConversation)"
                            type="button"
                            class="shrink-0 rounded-lg px-2 py-2 text-xs font-semibold text-emerald-400 hover:bg-slate-800"
                            @click="openMemberManager"
                        >
                            + Add
                        </button>
                    </header>

                    <div
                        v-if="incomingAlert"
                        class="pointer-events-none absolute right-4 top-20 z-40 max-w-xs rounded-xl border border-emerald-500/40 bg-slate-900 px-4 py-3 shadow-2xl"
                    >
                        <p class="text-xs font-semibold text-emerald-400">
                            {{ incomingAlert.sender }}
                        </p>
                        <p class="mt-1 truncate text-sm text-white">
                            {{ incomingAlert.body }}
                        </p>
                    </div>

                    <div
                        v-if="activeConversation"
                        class="shrink-0 border-b border-slate-800 bg-slate-900 px-3 py-2 sm:px-5"
                    >
                        <input
                            v-model="messageSearchQuery"
                            type="search"
                            placeholder="Search messages..."
                            autocomplete="off"
                            class="mx-auto block w-full max-w-4xl rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-emerald-500"
                            @input="searchMessages"
                        />
                    </div>

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
                            ref="messageScrollContainer"
                            class="min-h-0 flex-1 overflow-y-auto px-3 py-5 sm:px-6 sm:py-6"
                            @scroll="handleMessageScroll"
                        >
                            <div
                                class="mx-auto flex w-full max-w-4xl flex-col gap-4"
                            >
                                <div
                                    v-if="loadingOlderMessages"
                                    class="py-1 text-center text-xs text-slate-500"
                                >
                                    Loading older messages...
                                </div>

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
                                                class="absolute -top-9 z-10 hidden items-center gap-1 rounded-xl border border-slate-700 bg-slate-900 p-1 shadow-xl sm:group-hover:flex"
                                                :class="[
                                                    isMyMessage(message)
                                                        ? 'right-0'
                                                        : 'left-0',
                                                    {
                                                        '!flex sm:hidden':
                                                            activeReactionMessageId ===
                                                            message.id,
                                                    },
                                                ]"
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
                                                    :class="{
                                                        '!flex':
                                                            activeReactionMessageId ===
                                                            message.id,
                                                    }"
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
                                                @click="
                                                    toggleReactionPicker(
                                                        message,
                                                    )
                                                "
                                            >
                                                <p
                                                    v-if="isGroupConversation(activeConversation) && !isMyMessage(message)"
                                                    class="mb-1 text-xs font-semibold text-emerald-400"
                                                >
                                                    {{ message.sender?.name ?? 'Unknown user' }}
                                                </p>

                                                <p
                                                    v-if="message.attachment_url && message.attachment_mime?.startsWith('image/')"
                                                    class="mb-2"
                                                >
                                                    <img
                                                        :src="message.attachment_url"
                                                        :alt="message.attachment_name ?? 'Attached image'"
                                                        class="max-h-64 max-w-full cursor-zoom-in rounded-lg object-contain"
                                                        @click.stop="openImagePreview(message)"
                                                    />
                                                </p>

                                                <video
                                                    v-if="message.attachment_url && message.attachment_mime?.startsWith('video/')"
                                                    :src="message.attachment_url"
                                                    controls
                                                    playsinline
                                                    class="mb-2 max-h-72 max-w-full rounded-lg"
                                                ></video>

                                                <a
                                                    v-if="message.attachment_url && !message.attachment_mime?.startsWith('image/') && !message.attachment_mime?.startsWith('video/')"
                                                    :href="message.attachment_url"
                                                    target="_blank"
                                                    rel="noopener"
                                                    class="mb-2 block break-all text-sm underline"
                                                >
                                                    {{ message.attachment_name ?? 'Download attachment' }}
                                                </a>

                                                <p
                                                    v-if="message.body"
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

                                                    <span
                                                        v-if="isMyMessage(message) && message.read_at"
                                                        class="ml-1 text-[10px]"
                                                        :class="isMyMessage(message) ? 'text-slate-700' : 'text-slate-500'"
                                                    >
                                                        Read
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
                            <div
                                v-if="otherUserTyping"
                                class="mx-auto mb-2 max-w-4xl text-xs text-slate-500"
                            >
                                {{
                                    getOtherUser(
                                        activeConversation,
                                    )?.name ?? 'Someone'
                                }} is typing...
                            </div>

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

                                <input
                                    ref="messageFileInput"
                                    type="file"
                                    accept="image/*,video/*,.pdf,.doc,.docx,.txt"
                                    class="hidden"
                                    @change="selectMessageFile"
                                />

                                <button
                                    type="button"
                                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-slate-700 bg-slate-800 text-lg transition hover:bg-slate-700"
                                    title="Attach file"
                                    @click="messageFileInput?.click()"
                                >
                                    📎
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
                                    @input="handleTyping"
                                />

                                <!-- SEND -->

                                <button
                                    type="submit"
                                    :disabled="
                                        sendingMessage ||
                                        (!messageBody.trim() && !selectedMessageFile)
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

    <div
        v-if="showGroupMembers"
        class="fixed inset-0 z-[85] flex items-center justify-center bg-black/70 p-4"
        @click.self="closeGroupMembers"
    >
        <div class="max-h-[80dvh] w-full max-w-md overflow-y-auto rounded-2xl border border-slate-700 bg-slate-900 p-5 shadow-2xl">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold">Group members</h2>
                <button
                    type="button"
                    class="text-2xl text-slate-400 hover:text-white"
                    aria-label="Close group members"
                    @click="closeGroupMembers"
                >
                    ×
                </button>
            </div>

            <div class="mt-5 divide-y divide-slate-800 rounded-xl border border-slate-700">
                <div
                    v-for="member in activeConversation?.users ?? []"
                    :key="member.id"
                    class="flex items-center gap-3 px-3 py-3"
                >
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-500 font-bold text-slate-950">
                        {{ getUserInitial(member) }}
                    </div>
                    <div class="min-w-0">
                        <p class="truncate font-semibold text-white">{{ member.name }}</p>
                        <p class="truncate text-xs text-slate-500">@{{ member.username }}</p>
                    </div>
                    <span
                        v-if="Number(member.id) === Number(user?.id)"
                        class="ml-auto text-xs text-emerald-400"
                    >
                        You
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div
        v-if="showMemberManager"
        class="fixed inset-0 z-[80] flex items-center justify-center bg-black/70 p-4"
        @click.self="closeMemberManager"
    >
        <form
            class="max-h-[90dvh] w-full max-w-md overflow-y-auto rounded-2xl border border-slate-700 bg-slate-900 p-5 shadow-2xl"
            @submit.prevent="addMembersToGroup"
        >
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold">Add group members</h2>
                <button type="button" class="text-2xl text-slate-400" @click="closeMemberManager">×</button>
            </div>

            <div class="mt-5 overflow-hidden rounded-xl border border-slate-700">
                <button
                    v-for="friend in friends"
                    :key="friend.id"
                    v-show="!isConversationMember(friend)"
                    type="button"
                    class="flex w-full items-center justify-between border-b border-slate-800 px-3 py-3 text-left last:border-b-0 hover:bg-slate-800"
                    @click="toggleAdditionalMember(friend)"
                >
                    <span class="text-sm text-white">{{ friend.name }}</span>
                    <span class="text-xs text-emerald-400">
                        {{ additionalMembers.some((member) => Number(member.id) === Number(friend.id)) ? 'Added' : 'Add' }}
                    </span>
                </button>
            </div>

            <p v-if="!friends.some((friend) => !isConversationMember(friend))" class="mt-3 text-sm text-slate-500">
                All your friends are already in this group.
            </p>

            <button
                type="submit"
                :disabled="!additionalMembers.length"
                class="mt-5 w-full rounded-xl bg-emerald-500 px-4 py-3 font-bold text-slate-950 disabled:cursor-not-allowed disabled:opacity-50"
            >
                Add selected members
            </button>
        </form>
    </div>

    <div
        v-if="showGroupCreator"
        class="fixed inset-0 z-[80] flex items-center justify-center bg-black/70 p-4"
        @click.self="closeGroupCreator"
    >
        <form
            class="max-h-[90dvh] w-full max-w-md overflow-y-auto rounded-2xl border border-slate-700 bg-slate-900 p-5 shadow-2xl"
            @submit.prevent="createGroup"
        >
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold">Create group</h2>
                <button type="button" class="text-2xl text-slate-400" @click="closeGroupCreator">×</button>
            </div>

            <input
                v-model="groupName"
                required
                maxlength="100"
                placeholder="Group name"
                class="mt-5 w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-white outline-none focus:border-emerald-500"
            />

            <p class="mt-4 text-xs font-semibold uppercase tracking-wider text-slate-500">
                Choose from your friends
            </p>

            <div v-if="loadingFriends" class="mt-3 text-sm text-slate-500">
                Loading friends...
            </div>

            <div v-else-if="friends.length" class="mt-2 overflow-hidden rounded-xl border border-slate-700">
                <button
                    v-for="friend in friends"
                    :key="friend.id"
                    type="button"
                    class="flex w-full items-center justify-between border-b border-slate-800 px-3 py-2 text-left last:border-b-0 hover:bg-slate-800"
                    @click="toggleGroupMember(friend)"
                >
                    <span class="text-sm text-white">{{ friend.name }}</span>
                    <span class="text-xs text-emerald-400">
                        {{ isGroupMember(friend) ? 'Added' : 'Add' }}
                    </span>
                </button>
            </div>

            <p v-else class="mt-3 text-sm text-slate-500">
                Add friends from user search before creating a group.
            </p>

            <div v-if="groupMembers.length" class="mt-3 flex flex-wrap gap-2">
                <span
                    v-for="member in groupMembers"
                    :key="member.id"
                    class="rounded-full bg-emerald-500 px-3 py-1 text-xs font-semibold text-slate-950"
                >
                    {{ member.name }}
                </span>
            </div>

            <button
                type="submit"
                :disabled="loadingConversations || groupMembers.length === 0"
                class="mt-5 w-full rounded-xl bg-emerald-500 px-4 py-3 font-bold text-slate-950 disabled:cursor-not-allowed disabled:opacity-50"
            >
                {{ loadingConversations ? 'Creating...' : 'Create group' }}
            </button>
        </form>
    </div>

    <div
        v-if="showProfile"
        class="fixed inset-0 z-[90] flex items-center justify-center bg-black/70 p-4"
        @click.self="closeProfile"
    >
        <form
            class="max-h-[90dvh] w-full max-w-md overflow-y-auto rounded-2xl border border-slate-700 bg-slate-900 p-5 shadow-2xl sm:p-7"
            @submit.prevent="updateProfile"
        >
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold text-white">Your profile</h2>
                    <p class="mt-1 text-sm text-slate-500">Update your account details</p>
                </div>

                <button
                    type="button"
                    class="text-2xl text-slate-400 hover:text-white"
                    aria-label="Close profile"
                    @click="closeProfile"
                >
                    ×
                </button>
            </div>

            <div
                v-if="error"
                class="mt-5 rounded-lg border border-red-900 bg-red-950/50 px-3 py-2 text-sm text-red-300"
            >
                {{ error }}
            </div>

            <label class="mt-5 block text-sm font-medium text-slate-300">Full name</label>
            <input
                v-model="profileForm.name"
                required
                class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-white outline-none focus:border-emerald-500"
            />

            <label class="mt-4 block text-sm font-medium text-slate-300">Username</label>
            <input
                v-model="profileForm.username"
                required
                class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-white outline-none focus:border-emerald-500"
            />

            <label class="mt-4 block text-sm font-medium text-slate-300">Email</label>
            <input
                v-model="profileForm.email"
                type="email"
                required
                class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-white outline-none focus:border-emerald-500"
            />

            <label class="mt-4 block text-sm font-medium text-slate-300">New password</label>
            <input
                v-model="profileForm.password"
                type="password"
                minlength="8"
                autocomplete="new-password"
                placeholder="Leave blank to keep current password"
                class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-white outline-none placeholder:text-slate-500 focus:border-emerald-500"
            />

            <input
                v-model="profileForm.password_confirmation"
                type="password"
                minlength="8"
                autocomplete="new-password"
                placeholder="Confirm new password"
                class="mt-3 w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-white outline-none placeholder:text-slate-500 focus:border-emerald-500"
            />

            <button
                type="submit"
                :disabled="savingProfile"
                class="mt-5 w-full rounded-xl bg-emerald-500 px-4 py-3 font-bold text-slate-950 transition hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-50"
            >
                {{ savingProfile ? 'Saving...' : 'Save profile' }}
            </button>
        </form>
    </div>

    <div
        v-if="previewImage"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-black/90 p-4"
        role="dialog"
        aria-modal="true"
        :aria-label="previewImage.name"
        @click.self="closeImagePreview"
    >
        <button
            type="button"
            class="absolute right-4 top-4 flex h-11 w-11 items-center justify-center rounded-full bg-white/10 text-2xl text-white transition hover:bg-white/20"
            aria-label="Close image preview"
            @click="closeImagePreview"
        >
            ×
        </button>

        <img
            :src="previewImage.url"
            :alt="previewImage.name"
            class="max-h-[90vh] max-w-[95vw] object-contain"
        />
    </div>
</template>
