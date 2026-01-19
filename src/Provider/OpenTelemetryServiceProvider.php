<?php

namespace Iqtool\LaravelSignoz\Provider;

use Illuminate\Support\ServiceProvider;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Export\Http\PsrTransportFactory;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOffSampler;
use OpenTelemetry\SDK\Trace\Sampler\TraceIdRatioBasedSampler;
use OpenTelemetry\SDK\Common\Time\ClockFactory;
use OpenTelemetry\SDK\Logs\LoggerProvider;
use OpenTelemetry\SDK\Logs\Processor\BatchLogRecordProcessor;
use OpenTelemetry\SDK\Sdk;

class OpenTelemetryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__ . '/../config/opentelemetry.php',
            'opentelemetry'
        );

        // Register tracer interface
        $this->app->singleton(TracerInterface::class, function ($app) {
            return $this->createTracer();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/opentelemetry.php' => config_path('opentelemetry.php'),
        ], 'laravel-signoz-config');

        // Register global tracer provider using SDK builder
        $tracerProvider = $this->createTracerProvider();
        $meterProvider = $this->createMeterProvider();
        $loggerProvider = $this->createLoggerProvider();

        Sdk::builder()
            ->setTracerProvider($tracerProvider)
            ->setMeterProvider($meterProvider)
            ->setLoggerProvider($loggerProvider)
            ->setAutoShutdown(true)
            ->buildAndRegisterGlobal();

        // Explicitly handle DB instrumentation if needed and if not handled by auto-discovery
        if (config('opentelemetry.instrumentation.database.enabled')) {
            // Note: If using open-telemetry/opentelemetry-auto-laravel, this should be automatic
            // provided the extension is loaded or the auto-instrumentation is active.
            // If manual instrumentation is required, instantiate it here.
        }
    }

    /**
     * Create the TracerProvider with configured exporters
     */
    private function createTracerProvider(): TracerProvider
    {
        $resource = $this->createResource();
        $sampler = $this->createSampler();

        $tracerProvider = TracerProvider::builder()
            ->setResource($resource)
            ->setSampler($sampler);

        // Add exporters based on configuration
        // We now primarily support OTLP
        $exporterType = config('opentelemetry.exporter.type');

        if ($exporterType === 'otlp' || config('opentelemetry.exporter.otlp.enabled')) {
            $tracerProvider->addSpanProcessor(
                new BatchSpanProcessor($this->createOtlpTraceExporter(), ClockFactory::getDefault())
            );
        }

        return $tracerProvider->build();
    }

    private function createMeterProvider(): \OpenTelemetry\SDK\Metrics\MeterProviderInterface
    {
        $resource = $this->createResource();

        $meterProvider = \OpenTelemetry\SDK\Metrics\MeterProvider::builder()
            ->setResource($resource);

        if (config('opentelemetry.exporter.otlp.enabled')) {
            $reader = new \OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader(
                $this->createOtlpMetricExporter()
            );
            $meterProvider->addReader($reader);
        }

        return $meterProvider->build();
    }

    private function createLoggerProvider(): LoggerProvider
    {
        $resource = $this->createResource();

        $loggerProvider = LoggerProvider::builder()
            ->setResource($resource);

        // Add exporters based on configuration
        if (config('opentelemetry.exporter.otlp.enabled')) {
            $loggerExporter = $this->createOtlpLogExporter();
            $logProcessor = new BatchLogRecordProcessor($loggerExporter, ClockFactory::getDefault());
            $loggerProvider->addLogRecordProcessor($logProcessor);
        }

        return $loggerProvider->build();
    }

    /**
     * Create OTLP Trace Exporter
     */
    private function createOtlpTraceExporter(): SpanExporter
    {
        $endpoint = config('opentelemetry.exporter.otlp.endpoint') . '/v1/traces';
        $protocol = config('opentelemetry.exporter.otlp.protocol'); // http/protobuf or http/json
        $contentType = $protocol === 'http/json' ? 'application/json' : 'application/x-protobuf';

        return new SpanExporter(
            PsrTransportFactory::discover()->create($endpoint, $contentType)
        );
    }

    /**
     * Create OTLP Metric Exporter
     */
    private function createOtlpMetricExporter(): \OpenTelemetry\SDK\Metrics\MetricExporterInterface
    {
        $endpoint = config('opentelemetry.exporter.otlp.endpoint') . '/v1/metrics';
        $protocol = config('opentelemetry.exporter.otlp.protocol');
        $contentType = $protocol === 'http/json' ? 'application/json' : 'application/x-protobuf';

        return new \OpenTelemetry\Contrib\Otlp\MetricExporter(
            PsrTransportFactory::discover()->create($endpoint, $contentType)
        );
    }

    /**
     * Create OTLP Log Exporter
     */
    private function createOtlpLogExporter(): \OpenTelemetry\SDK\Logs\LogRecordExporterInterface
    {
        $endpoint = config('opentelemetry.exporter.otlp.endpoint') . '/v1/logs';
        $protocol = config('opentelemetry.exporter.otlp.protocol');
        $contentType = $protocol === 'http/json' ? 'application/json' : 'application/x-protobuf';

        return new \OpenTelemetry\Contrib\Otlp\LogsExporter(
            PsrTransportFactory::discover()->create($endpoint, $contentType)
        );
    }

    /**
     * Create resource with service information
     */
    private function createResource(): ResourceInfo
    {
        $attributes = array_merge(
            [
                'service.name' => config('opentelemetry.service_name'),
                'service.version' => config('opentelemetry.service_version'),
            ],
            config('opentelemetry.resource_attributes', [])
        );

        return ResourceInfoFactory::defaultResource()->merge(
            ResourceInfo::create(Attributes::create($attributes))
        );
    }

    /**
     * Create sampler based on configuration
     */
    private function createSampler()
    {
        $samplerType = config('opentelemetry.traces.sampler');

        return match ($samplerType) {
            'always_on' => new AlwaysOnSampler(),
            'always_off' => new AlwaysOffSampler(),
            'traceidratio' => new TraceIdRatioBasedSampler(
                config('opentelemetry.traces.sampler_arg', 1.0)
            ),
            default => new AlwaysOnSampler(),
        };
    }

    /**
     * Create tracer instance
     */
    private function createTracer(): TracerInterface
    {
        return $this->createTracerProvider()->getTracer(
            config('opentelemetry.service_name'),
            config('opentelemetry.service_version')
        );
    }
}
