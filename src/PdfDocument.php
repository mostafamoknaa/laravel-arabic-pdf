<?php

namespace MostafaMoknaa\ArabicPdf;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Response;
use RuntimeException;

class PdfDocument
{
    private Dompdf $dompdf;

    private ?string $html = null;

    private bool $rendered = false;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly Arabic $arabic,
        private readonly array $config = [],
        private readonly ?ViewFactory $view = null,
    ) {
        $this->dompdf = new Dompdf($this->makeOptions());
        $this->setPaper($config['paper'] ?? 'a4', $config['orientation'] ?? 'portrait');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $mergeData
     */
    public function loadView(string $view, array $data = [], array $mergeData = []): static
    {
        if ($this->view === null) {
            throw new RuntimeException('A view factory is required to render views. Use loadHTML() outside Laravel.');
        }

        return $this->loadHTML($this->view->make($view, $data, $mergeData)->render());
    }

    public function loadHTML(string $html): static
    {
        $this->html = $this->arabic->html($html);
        $this->rendered = false;

        return $this;
    }

    public function loadFile(string $path): static
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read HTML file [{$path}].");
        }

        return $this->loadHTML($contents);
    }

    /**
     * @param  string|array<int, float>  $paper  A paper name (a4, letter, ...) or [x1, y1, x2, y2] in points.
     */
    public function setPaper(string|array $paper, string $orientation = 'portrait'): static
    {
        $this->dompdf->setPaper($paper, $orientation);
        $this->rendered = false;

        return $this;
    }

    /**
     * Set dompdf options, e.g. setOption('isRemoteEnabled', true).
     *
     * @param  string|array<string, mixed>  $key
     */
    public function setOption(string|array $key, mixed $value = null): static
    {
        $this->dompdf->getOptions()->set(is_array($key) ? $key : [$key => $value]);
        $this->rendered = false;

        return $this;
    }

    /**
     * The processed HTML that is sent to dompdf, useful for debugging.
     */
    public function html(): string
    {
        return $this->html ?? '';
    }

    public function getDompdf(): Dompdf
    {
        return $this->dompdf;
    }

    public function render(): static
    {
        if ($this->html === null) {
            throw new RuntimeException('Nothing to render. Call loadView() or loadHTML() first.');
        }

        if (! $this->rendered) {
            $this->dompdf->loadHtml($this->html, 'UTF-8');
            $this->dompdf->render();
            $this->rendered = true;
        }

        return $this;
    }

    public function output(): string
    {
        return (string) $this->render()->dompdf->output();
    }

    public function save(string $path): static
    {
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($path, $this->output());

        return $this;
    }

    public function download(string $filename = 'document.pdf'): Response
    {
        return $this->response($filename, 'attachment');
    }

    public function stream(string $filename = 'document.pdf'): Response
    {
        return $this->response($filename, 'inline');
    }

    private function response(string $filename, string $disposition): Response
    {
        $fallback = preg_replace('/[^\x20-\x7E]|["\\\\]/', '_', $filename) ?: 'document.pdf';

        return new Response($this->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf(
                '%s; filename="%s"; filename*=UTF-8\'\'%s',
                $disposition,
                $fallback,
                rawurlencode($filename),
            ),
        ]);
    }

    private function makeOptions(): Options
    {
        $settings = array_filter(
            $this->config['dompdf'] ?? [],
            static fn (mixed $value): bool => $value !== null,
        );

        $settings['defaultFont'] ??= $this->config['default_font'] ?? 'DejaVu Sans';

        foreach (['fontDir', 'fontCache'] as $key) {
            if (isset($settings[$key]) && ! is_dir($settings[$key])) {
                @mkdir($settings[$key], 0755, true);
            }
        }

        return new Options($settings);
    }
}
