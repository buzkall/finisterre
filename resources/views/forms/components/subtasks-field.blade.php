<x-dynamic-component
        :component="$getFieldWrapperView()"
        :field="$field"
>

    <div x-data="{
        subtasks: $wire.entangle('{{ $getStatePath() }}'),

        addSubtask() {
            this.subtasks.push({ title: '', completed: false });
        },

        removeSubtask(index) {
            this.subtasks.splice(index, 1);
        },
    }">
        <template x-for="(subtask, index)  in subtasks" :key="index">
            <div class="flex justify-start items-center mb-2">
                <input type="checkbox" x-model="subtask.completed" class="mr-2">

                <input type="text" x-model="subtask.title"
                       class="border-none focus:ring-0 bg-transparent text-sm flex-grow"
                       :class="{ 'line-through text-gray-500': subtask.completed }"
                       placeholder="{{ __('finisterre::finisterre.subtasks.placeholder') }}"/>

                <button @click="removeSubtask(index)" type="button" class="text-red-500 text-xs ml-2">
                    ✕
                </button>
            </div>
        </template>

        <button @click="addSubtask()" type="button" class="text-blue-500 text-sm mt-2">
            + {{ __('finisterre::finisterre.subtasks.add') }}
        </button>
    </div>
</x-dynamic-component>