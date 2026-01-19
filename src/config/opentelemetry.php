<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OpenTelemetry Service Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration file manages OpenTelemetry settings for distributed
    | tracing, metrics, and logging.
    |
    */

    'service_name' => env('OTEL_SERVICE_NAME', 'laravel-otel-app'),
    'service_version' => env('OTEL_SERVICE_VERSION', '1.0.0'),
    'environment' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Exporter Configuration
    |--------------------------------------------------------------------------
    |
    | Choose which exporter(s) to use: 'jaeger', 'zipkin', or 'both'
    | Each exporter sends telemetry data to its respective backend.
    |
    */

    'exporter' => [
        'type' => env('OTEL_EXPORTER_TYPE', 'otlp'), // 'otlp'

        'otlp' => [
            'enabled' => env('OTEL_EXPORTER_OTLP_ENABLED', true),
            'endpoint' => env('OTEL_EXPORTER_OTLP_ENDPOINT', 'http://localhost:4318'),
            'protocol' => env('OTEL_EXPORTER_OTLP_PROTOCOL', 'http/protobuf'),
            'headers' => env('OTEL_EXPORTER_OTLP_HEADERS', ''), // e.g. "Authorization=Bearer <token>"
        ],

        'signoz' => [
            'enabled' => env('OTEL_EXPORTER_SIGNOZ_ENABLED', false),
            // Legacy/Specific SigNoz settings if needed, but standard OTLP should suffice.
            // keeping for backward compat or specific overrides
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Instrumentation Configuration
    |--------------------------------------------------------------------------
    |
    | Enable/Disable specific instrumentations.
    |
    */

    'instrumentation' => [
        'database' => [
            'enabled' => env('OTEL_INSTRUMENTATION_DB_ENABLED', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sampling Configuration
    |--------------------------------------------------------------------------
    |
    | Control which traces are collected:
    | - 'always_on': Sample all traces
    | - 'always_off': Sample no traces
    | - 'traceidratio': Sample based on probability (0.0 to 1.0)
    |
    */

    'traces' => [
        'sampler' => env('OTEL_TRACES_SAMPLER', 'always_on'),
        'sampler_arg' => env('OTEL_TRACES_SAMPLER_ARG', 1.0), // For traceidratio sampler
    ],

    /*
    |--------------------------------------------------------------------------
    | Resource Attributes
    |--------------------------------------------------------------------------
    |
    | Additional attributes to attach to all telemetry data.
    | These help identify and filter traces in the observability backend.
    |
    */

    'resource_attributes' => [
        'deployment.environment' => env('APP_ENV', 'production'),
        'host.name' => gethostname(),
    ],


];
