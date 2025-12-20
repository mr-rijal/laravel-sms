<?php

namespace MrRijal\LaravelSms;

class SmsMessage
{
    public array $to = [];
    public ?string $text = null;
    public ?string $templateId = null;
    public array $variables = [];

    public function to(string|array $numbers): self
    {
        $this->to = array_unique(array_merge($this->to, (array) $numbers));
        return $this;
    }

    public function message(string $text): self
    {
        $this->text = $text;
        return $this;
    }

    public function template(string $templateId, array $vars = []): self
    {
        $this->templateId = $templateId;
        $this->variables = $vars;
        return $this;
    }
}
