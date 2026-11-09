<p align="center"><img src="art/logo.svg" width="320" alt="LaraGram Armada"></p>

# LaraGram Armada

Armada provides a Docker environment for LaraGram that works on macOS, Windows (WSL2), and Linux, with PHP 8.3, 8.4, and 8.5. Apart from Docker, no additional software is required.

- Run the official `telegram-bot-api` server or the LaraGram API Server next to your bot.
- Run Surge, MTProto, Luna SSR, Sentinel, Watchdog, queues, and the scheduler with a single setting.
- Generate production images and a Compose file for deployment.

```shell
composer require laraxgram/armada --dev

php laragram armada:install
./vendor/bin/armada up
```

## Official Documentation

Documentation for Armada can be found on the [LaraGram website](https://laraxgram.github.io/v4/armada).

## License

LaraGram Armada is open-sourced software licensed under the [MIT license](LICENSE).
