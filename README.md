# Laravel SigNoz Integration

A Laravel package to integrate OpenTelemetry for SigNoz, providing Traces, Metrics, and Logs via OTLP.

## Installation

Install the package via composer:

```bash
composer require iqtool/laravel-signoz
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=laravel-signoz-config
```

## usage

Configure your `.env` file to point to your SigNoz OTLP collector:

```env
OTEL_SERVICE_NAME=your-app-name
OTEL_EXPORTER_TYPE=otlp
OTEL_EXPORTER_OTLP_ENDPOINT=http://locahost:4318
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
OTEL_INSTRUMENTATION_DB_ENABLED=true
```

## Features

- **Traces**: Auto-instrumentation for Laravel requests, jobs, and more via `opentelemetry-auto-laravel`.
- **Metrics**: Standard system and application metrics.
- **Logs**: Application logs sent to SigNoz.
- **Database**: Automatic query instrumentation.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
