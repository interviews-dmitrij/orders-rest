<?php

declare(strict_types=1);

namespace App\Exception;

use Throwable;

interface ApiProblemInterface extends Throwable
{
    /**
     * Stable slug that identifies the problem type across deployments;
     * the listener concatenates `${PROBLEM_TYPE_BASE_URI}/${slug}`.
     */
    public function problemSlug(): string;

    public function httpStatus(): int;

    public function title(): string;
}
