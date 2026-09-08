<?php

namespace App\Http\Requests\Milestone;

class UpdateMilestoneRequest extends StoreMilestoneRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('milestones.update');
    }
}
