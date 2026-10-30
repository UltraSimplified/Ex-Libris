<?php

namespace App\Nova\Actions;

use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;
use Throwable;

/**
 * An action that moves orders on a step, run on as many as are selected.
 *
 * Orders the step does not apply to - already posted, or for collection
 * rather than post - are left as they are and counted, so running it over
 * a mixed selection is safe.
 */
abstract class OrderStep extends Action
{
    /**
     * What the step does, as the message says it: "posted".
     */
    abstract protected function pastTense(): string;

    /**
     * Whether the step can be taken on this order now.
     */
    abstract public function applies(Order $order): bool;

    /**
     * Take the step.
     */
    abstract protected function apply(Order $order, ActionFields $fields): void;

    public function __construct()
    {
        $this->canRun(fn (NovaRequest $request, Order $order): bool => $this->applies($order));
    }

    /**
     * @param  Collection<int, Order>  $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        $done = 0;
        $skipped = 0;
        $failures = [];

        foreach ($models as $order) {
            if (! $this->applies($order)) {
                $skipped++;

                continue;
            }

            try {
                $this->apply($order, $fields);
                $done++;
            } catch (Throwable $exception) {
                report($exception);
                $failures[] = $order->reference.': '.$exception->getMessage();
            }
        }

        if ($failures !== []) {
            return ActionResponse::danger(implode(' ', $failures));
        }

        if ($done === 0) {
            return ActionResponse::danger('None of those orders can be '.$this->pastTense().' now.');
        }

        $message = ucfirst($this->pastTense()).' '.$done.' '.Str::plural('order', $done).'.';

        if ($skipped > 0) {
            $message .= ' Left '.$skipped.' that could not be.';
        }

        return ActionResponse::message($message);
    }
}
