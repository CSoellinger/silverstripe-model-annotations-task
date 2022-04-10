<?php

namespace CSoellinger\SilverStripe\ModelAnnotation\Test\E2E\Task;

use SilverStripe\Core\Environment;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Security\Member;

/**
 * Simple functional test for our task.
 *
 * @internal
 *
 * @covers \CSoellinger\SilverStripe\ModelAnnotation\Task\ModelAnnotationTask
 */
class ModelAnnotationTaskTest extends FunctionalTest
{
    /**
     * Call the task by url.
     */
    public function testCallingTaskByUrl(): void
    {
        $member = Member::get_by_id(1);
        $this->logInAs($member);

        $url = '/dev/tasks/CSoellinger-SilverStripe-ModelAnnotation-Task-ModelAnnotationTask';
        $params = [
            'dryRun=1',
            'dataClass=CSoellinger\\SilverStripe\\ModelAnnotation\\Test\\Unit\\Team',
            'quiet=1',
        ];
        $page = $this->get($url . '?' . implode('&', $params));

        $this->assertEquals(200, $page->getStatusCode());
    }
}
