<?php

use Buzkall\Finisterre\Policies\FinisterreTaskReportPolicy;
use Illuminate\Contracts\Auth\Authenticatable;
use Mockery;

beforeEach(function() {
    $this->policy = new FinisterreTaskReportPolicy;
    $this->user = Mockery::mock(Authenticatable::class);
});

it('returns false when restrict_task_reports_callback returns true', function() {
    config(['finisterre.restrict_task_reports_callback' => function($user) {
        return true;
    }]);

    $result = $this->policy->before($this->user);

    expect($result)->toBeFalse();
});

it('returns null when no restrict_task_reports_callback is configured', function() {
    config(['finisterre.restrict_task_reports_callback' => null]);

    $result = $this->policy->before($this->user);

    expect($result)->toBeNull();
});

it('returns null when restrict_task_reports_callback returns false', function() {
    config(['finisterre.restrict_task_reports_callback' => function($user) {
        return false;
    }]);

    $result = $this->policy->before($this->user);

    expect($result)->toBeNull();
});

it('executes callback with user parameter', function() {
    $callbackCalled = false;
    $callbackUser = null;

    config(['finisterre.restrict_task_reports_callback' => function($user) use (&$callbackCalled, &$callbackUser) {
        $callbackCalled = true;
        $callbackUser = $user;

        return false;
    }]);

    $result = $this->policy->before($this->user);

    expect($callbackCalled)->toBeTrue();
    expect($callbackUser)->toBe($this->user);
    expect($result)->toBeNull();
});
