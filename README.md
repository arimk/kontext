# Kontext

A PHP-based app to test Flux Kontext AI editing model, through Replicate

## Features

- **Ad Brainstormer**: Tool for generating and brainstorming advertising content automatically with a product image and some directions (using openAI ChatGPT)
- **Conversational Editing**: Interactive content editing interface
- **Multi-Image Processing**: Handle multiple image uploads to edit

## Project Structure

```
├── backend/         # Backend processing scripts
├── config/         # Configuration files
├── css/           # Stylesheets
├── js/            # JavaScript files
├── pages/         # Main application pages
├── uploads/       # File upload directory
├── auth.php       # Authentication handling
├── index.php      # Main application entry point
├── login.php      # Login page
├── _header.php    # Common header template
└── _footer.php    # Common footer template
```

## Requirements

- PHP 7.4 or higher with GD
- Replicate key and openAI key (for the ad brainstorm)

## Installation

1. Clone the repository
2. Configure your web server to point to the project directory
3. Set up configuration in `config/config.php` using `config.example.php` (keys and login/password especially)
4. Ensure the `uploads` directory is writable by the web server
5. Access the application through your web browser


Vibe coded with Cursor