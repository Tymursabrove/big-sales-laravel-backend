<?php

namespace App\Call;

use App\Models\Call;
use Closure;
use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use Illuminate\Support\Facades\Blade;
use OpenAI;
use OpenAI\Client;

class Assistant implements EventEmitterInterface
{
    use EventEmitterTrait;

    private const MODEL = 'mixtral-8x7b-32768';

    protected Client $client;

    protected array $messages;

    protected string $tmpMessage = '';

    public function __construct(protected Call $call)
    {
        $this->client = OpenAI::factory()
            ->withApiKey(config('services.groq.key'))
            ->withBaseUri('https://api.groq.com/openai/v1/')
            ->make();

        $this->addMessage('system', Blade::render('ai_templates.assistant-system-message', [
            'call' => $this->call,
            'caller' => $this->call->caller,
        ]));
    }

    public function sendUserMessage(string $message)
    {
        $messageIterator = $this->addUserMessage($message)->send();

        $this->emit('speak', [$messageIterator]);
    }

    public function dumpTranscription(): string
    {
        $messages = collect($this->messages);

        return $messages->reject(fn ($message) => $message['role'] === 'system')->map(function ($message) {
            return "{$message['role']}: {$message['content']}";
        })->join(PHP_EOL);
    }

    protected function addMessage(string $role, string $content): static
    {
        $this->messages[] = compact('role', 'content');

        return $this;
    }

    protected function addUserMessage(string $message): static
    {
        $this->addMessage('user', $message);

        return $this;
    }

    protected function addAssistantMessage(string $message): static
    {
        $this->addMessage('assistant', $message);

        return $this;
    }

    protected function send(): Closure
    {

        $stream = $this->client->chat()->createStreamed([
            'model' => self::MODEL,
            'temperature' => 0.5,
            'messages' => $this->messages,
        ]);

        return function () use ($stream) {
            foreach ($stream as $response) {
                /** @var OpenAI\Responses\Chat\CreateStreamedResponseChoice $choice */
                $choice = $response->choices[0];
                $text = $choice->delta->content;

                $this->tmpMessage .= $text;

                if(! is_null($choice->finishReason)) {
                    $this->addAssistantMessage($this->tmpMessage);
                    $this->tmpMessage = '';
                }

                if(is_null($text) || $text === '') {
                    continue;
                }

                yield $text;
            }
        };
    }
}
