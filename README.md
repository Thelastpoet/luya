# Luya

## Description

Luya is a WordPress plugin that utilizes OpenAI models to transform your draft posts. The plugin fetches draft posts, generates a summary of the content using OpenAI, creates a new title and content using AI, and publishes the post automatically.

## Installation

1. Download the plugin files.
2. Extract the plugin .zip file.
3. Upload the `luya` folder to your `/wp-content/plugins/` directory.
4. Activate the plugin from the "Plugins" page in WordPress.

## Usage

Luya runs automatically using WordPress Cron. It fetches draft posts one by one and processes them through OpenAI to create new content from the existing one. After the draft is processed and a new title assinged, Luya publishes the post.

## Features

* Fetch all draft posts
* Summarize post content using OpenAI
* Creates new content from the summary
* Generate a new title using OpenAI
* Update post
* Publishes the processed draft post

## NOTES
* GPT4 does a good job and can publish longer posts
* GPT-3.5-Turbo does a good job and it is way cheaper than any new model
* GPT3 does a good job though the output is not as good as the other chat models.

## Requirements

To use this plugin, you need an API key from OpenAI.

## Configuration

Configure the plugin settings by adding your OpenAI API key.

## License

Luya is licensed under GPL v2 or later. See the `LICENSE` file for more details.

## Author

Ammanulah Emmanuel
Website: [https://ammanulah.com](https://ammanulah.com)
