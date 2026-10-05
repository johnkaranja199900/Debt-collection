<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageTemplate extends Model
{
    protected $fillable = ['name', 'channel', 'type', 'content', 'variables', 'status'];

    protected $casts = ['variables' => 'array'];

    /**
     * Deterministic variable rendering. Unknown or missing variables throw,
     * so nothing incomplete is ever sent to a customer (spec section 31).
     */
    public function render(array $data): string
    {
        $allowed = $this->variables ?? array_keys($data);
        $missing = [];

        $output = preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/i', function (array $m) use ($data, $allowed, &$missing) {
            $key = $m[1];
            if (! in_array($key, $allowed, true) || ! array_key_exists($key, $data)) {
                $missing[] = $key;

                return '';
            }

            return (string) $data[$key];
        }, $this->content);

        if ($missing) {
            throw new \InvalidArgumentException('Missing template variables: '.implode(', ', array_unique($missing)));
        }

        return $output;
    }
}
