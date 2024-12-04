<?php

return [
    'task'             => 'Task',
    'tasks'            => 'Tasks',
    'title'            => 'Title',
    'description'      => 'Description',
    'edit_description' => 'Edit description',
    'status'           => 'State',
    'priority'         => 'Priority',
    'due_at'           => 'Due at',
    'created_at'       => 'Created at',
    'updated_at'       => 'Updated at',
    'completed_at'     => 'Completed at',
    'attachments'      => 'Attachments',
    'user_id'          => 'Created by',
    'assignee_id'      => 'Assigned to',
    'assignee_name'    => 'Assigned to',
    'tags'             => 'Tags',
    'creator_name'     => 'Created by',
    'Low'              => 'Low',
    'Medium'           => 'Medium',
    'High'             => 'High',
    'Urgent'           => 'Urgent',
    'Open'             => 'Open',
    'OnHold'           => 'On hold',
    'Doing'            => 'Doing',
    'Done'             => 'Done',
    'Rejected'         => 'Rejected',
    'Backlog'          => 'Backlog',
    'save'             => 'Save',
    'cancel'           => 'Cancel',
    'create_task'      => 'Create task',
    'message'          => 'Message',
    'comments'         => [
        'title'         => 'Comments',
        'placeholder'   => 'Write a comment...',
        'add'           => 'Add comment',
        'empty'         => 'No comments',
        'delete'        => 'Delete comment',
        'notify'        => 'Notify to',
        'notify_hint'   => 'The selected users will receive an email notification.',
        'notifications' => [
            'created' => 'Comment created',
            'deleted' => 'Comment deleted',
        ],
    ],
    'notification' => [
        'subject'          => '[:priority] task :title',
        'greeting_new'     => 'New task :title',
        'greeting_changes' => 'Changes in task :title',
        'cta'              => 'View task',
        'changes'          => 'Changes',
    ],
    'comment_notification' => [
        'subject'  => 'New comment in task :title',
        'greeting' => 'New comment in task :title',
        'cta'      => 'View task',
    ],
];
