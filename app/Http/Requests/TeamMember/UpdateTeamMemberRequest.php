<?php

namespace App\Http\Requests\TeamMember;

class UpdateTeamMemberRequest extends StoreTeamMemberRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('team.update');
    }
}
