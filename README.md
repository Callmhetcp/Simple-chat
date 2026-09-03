# 💬 Simple Chat

<p align="center">
  <strong>A WhatsApp-style private messaging application built with Laravel 12, Vue.js, Tailwind CSS, and MySQL.</strong>
</p>

<p align="center">
  <a href="https://github.com/Calmhetcp/Simple-chat">
    <img src="https://img.shields.io/badge/GitHub-Calmhetcp%2FSimple--chat-black?logo=github" alt="GitHub Repository">
  </a>
  <img src="https://img.shields.io/badge/Laravel-12-red?logo=laravel" alt="Laravel 12">
  <img src="https://img.shields.io/badge/Vue.js-3-42b883?logo=vue.js" alt="Vue.js 3">
  <img src="https://img.shields.io/badge/Tailwind%20CSS-4-06B6D4?logo=tailwindcss" alt="Tailwind CSS">
  <img src="https://img.shields.io/badge/MySQL-Database-blue?logo=mysql" alt="MySQL">
</p>

---

## 📌 About the Project

**Simple Chat** is a private messaging application developed as a practical WhatsApp-style chat challenge.

The application allows registered users to search for other registered users, start private conversations, exchange messages, edit and delete their messages, react to messages, and keep track of unread messages.

The interface is designed to provide a familiar messaging experience across **mobile, tablet, and desktop devices**.

This project focuses on demonstrating practical full-stack Laravel development rather than building only a frontend interface.

---

## ✨ Features

### 🔐 Authentication

- User registration
- User login
- User logout
- Protected API endpoints
- Laravel Sanctum authentication

### 👥 User Search

- Search registered users
- Search by name
- Search by username
- Search by email
- Current user excluded from search results
- Start a conversation with another registered user

### 💬 Private Conversations

- One-to-one private conversations
- Create new conversations
- Open existing conversations
- Prevent duplicate private conversations
- Prevent users from messaging themselves
- Conversation history
- Latest message preview
- Private conversation authorization

### 📨 Messaging

- Send private messages
- View message history
- Edit messages
- Delete messages
- Message timestamps
- Read/unread message tracking
- Mark messages as read

### 🔴 Unread Messages

The application maintains a separate unread message count.

A message is considered unread when:

```text
read_at IS NULL
AND
sender_id != current_user_idework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
