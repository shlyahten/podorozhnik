# Telegram Bot for Podorozhnik

This repository contains a PHP-based Telegram bot designed to interact with the Podorozhnik transport card system. The bot allows users to manage their card data, view trip history, and track payments through simple Telegram commands.

## Features

- **Login and Token Management:**
  - Supports authentication using user-provided credentials (email and password).
  - Saves and refreshes tokens for seamless user experience.

- **Trip and Payment History:**
  - Fetches and displays trip history with detailed information (date, route, type, and cost).
  - Retrieves payment history with details on amount, status, and remaining balance.

- **Balance Calculation:**
  - Calculates the current balance based on trip and payment data.

## Requirements

- PHP 7.4 or higher
- Composer (for dependency management)
- Telegram Bot API token

## Installation

1. Clone this repository:
   ```bash
   git clone https://github.com/shlyahten/podorozhnik.git
   cd podorozhnik
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Set up your environment:
   - Create a `.env` file in the root directory.
   - Add your Telegram bot token:
     ```env
     BOT_TOKEN=your_telegram_bot_token
     ```

4. Set up a webhook for your Telegram bot:
   ```bash
   php setWebhook.php
   ```

5. Run the bot:
   ```bash
   php bot.php
   ```

## Commands

### User Commands

- **Login:**
  Send your credentials in the format `email:password` to authenticate.

- **Refresh Token:**
  Use `/refresh` to refresh your token.

- **Trip History:**
  Use `/trips` to fetch and display your trip history.

- **Payment History:**
  Use `/payments` to fetch and display your payment history.

- **Balance Calculation:**
  Use `/calc` to calculate your current balance.

### Developer Commands

- **Error Logs:**
  Errors are logged to `error_log.txt` in the root directory for debugging purposes.

## File Structure

- `bot.php`: Main bot logic.
- `setWebhook.php`: Script to set up the Telegram webhook.
- `db.txt`: Stores user tokens for the VPB system.
- `p_db.txt`: Stores user tokens for the Podorozhnik system.
- `error_log.txt`: Error log file.

## Contributing

Contributions are welcome! Please fork this repository and submit a pull request with your improvements.

## License

This project is licensed under the MIT License. See the LICENSE file for details.

---

For more information, contact the repository owner or create an issue in this repository.

