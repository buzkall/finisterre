<?php

return [
    'task'              => 'Task',
    'tasks'             => 'Tasks',
    'title'             => 'Title',
    'description'       => 'Description',
    'edit_description'  => 'Edit description',
    'status'            => 'State',
    'priority'          => 'Priority',
    'due_at'            => 'Due at',
    'created_at'        => 'Created at',
    'updated_at'        => 'Updated at',
    'completed_at'      => 'Completed at',
    'attachments'       => 'Attachments',
    'user_id'           => 'Created by',
    'assignee_id'       => 'Assigned to',
    'assignee_name'     => 'Assigned to',
    'tags'              => 'Tags',
    'creator_name'      => 'Created by',
    'Low'               => 'Low',
    'Medium'            => 'Medium',
    'High'              => 'High',
    'Urgent'            => 'Urgent',
    'Open'              => 'Open',
    'OnHold'            => 'On hold',
    'ToDeploy'          => 'To deploy',
    'Doing'             => 'Doing',
    'Done'              => 'Done',
    'Rejected'          => 'Rejected',
    'Backlog'           => 'Backlog',
    'save'              => 'Save',
    'cancel'            => 'Cancel',
    'create_task'       => 'Create task',
    'message'           => 'Message',
    'delete'            => 'Delete task',
    'archive'           => 'Archive',
    'archive_heading'   => 'Archive task to hide it from the main list',
    'unarchive'         => 'Unarchive',
    'unarchive_heading' => 'Unarchive task to show it in the main list',
    'comments'          => [
        'title'         => 'Comments',
        'placeholder'   => 'Write a comment...',
        'add'           => 'Add comment',
        'empty'         => 'No comments',
        'delete'        => 'Delete comment',
        'notify'        => 'Notify to',
        'notify_hint'   => 'The selected users will receive an email notification.',
        'notifications' => [
            'created'              => 'Comment created',
            'created_and_notified' => 'Comment created and notified to :notified',
            'deleted'              => 'Comment deleted',
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
    'filter' => [
        'label'                     => 'Filters',
        'cta'                       => 'Filter',
        'text'                      => 'Text',
        'clear'                     => 'Clear filters',
        'assignee'                  => 'Assignee',
        'text_description'          => 'Search by title and description',
        'show_archived'             => 'Show archived',
        'show_archived_description' => 'By default, archived tasks are hidden from the main list.',
    ],
    'subtasks' => [
        'label'       => 'Subtasks',
        'add'         => 'Add subtask',
        'placeholder' => 'Write a subtask...',
    ]
];
