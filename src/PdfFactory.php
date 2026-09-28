<?php

namespace MostafaMoknaa\ArabicPdf;

use Illuminate\Contracts\View\Factory as ViewFactory;

class PdfFactory
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly Arabic $arabic,
        private readonly array $config = [],
        private readonly ?ViewFactory $view = null,
    ) {}

    public function make(): PdfDocument
    {
        return new PdfDocument($this->arabic, $this->config, $this->view);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $mergeData
     */
    public function loadView(string $view, array $data = [], array $mergeData = []): PdfDocument
    {
        return $this->make()->loadView($view, $data, $mergeData);
    }

    public function loadHTML(string $html): PdfDocument
    {
        return $this->make()->loadHTML($html);
    }

    public function loadFile(string $path): PdfDocument
    {
        return $this->make()->loadFile($path);
    }
}
