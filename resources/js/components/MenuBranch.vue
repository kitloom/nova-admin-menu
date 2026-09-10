<template>
  <ul :class="depth === 0 ? 'space-y-2' : 'mt-2 space-y-1 pl-6 border-l border-gray-200 dark:border-gray-700'">
    <li v-for="(node, index) in nodes" :key="node.id || `${node.label}-${index}`">
      <div
        class="flex items-center justify-between gap-4 rounded border px-3 py-2"
        :class="[
          node.sortable
            ? 'cursor-move border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900'
            : 'border-dashed border-gray-200 dark:border-gray-700 opacity-60',
          node.hidden ? 'opacity-50' : '',
          drag.node === node ? 'opacity-40' : '',
          dropHint(node) === 'before' ? 'border-t-2 border-t-primary-500' : '',
          dropHint(node) === 'after' ? 'border-b-2 border-b-primary-500' : '',
          dropHint(node) === 'inside' ? 'ring-2 ring-primary-500' : '',
        ]"
        :draggable="node.sortable"
        @dragstart="onDragStart(node, $event)"
        @dragover="onDragOver(node, $event)"
        @drop="onDrop(node, $event)"
        @dragend="onDragEnd"
      >
        <div class="flex items-center gap-3 min-w-0">
          <span class="text-gray-400 text-xs w-6 shrink-0">{{ node.sortable ? '::' : '' }}</span>
          <span class="truncate" :class="[depth === 0 ? 'font-semibold' : '', node.hidden ? 'line-through' : '']">
            {{ node.label_override || node.label }}
          </span>
          <span
            v-if="node.moved"
            class="shrink-0 text-xs text-yellow-600 dark:text-yellow-500"
            :title="__('Moved out of the section the menu itself puts it in.')"
          >
            {{ __('moved') }}
          </span>
          <span
            v-if="node.label_override"
            class="shrink-0 text-xs text-gray-400"
            :title="__('Renamed. This is what the menu itself calls the entry.')"
          >
            ({{ node.label }})
          </span>
          <span class="shrink-0 rounded bg-gray-200 px-2 py-0.5 text-xs uppercase tracking-wide text-gray-600 dark:bg-gray-800 dark:text-gray-400">
            {{ node.type }}
          </span>
          <span
            v-if="node.via === 'label'"
            class="shrink-0 text-xs text-yellow-600 dark:text-yellow-500"
            :title="__('This entry has no path, so its order is remembered by its label and is lost if the locale changes.')"
          >
            {{ __('by label') }}
          </span>
        </div>

        <div class="flex shrink-0 items-center gap-3">
          <span v-if="!node.sortable" class="text-xs text-gray-400">
            {{ __('not sortable') }}
          </span>

          <button
            v-if="node.sortable"
            type="button"
            class="rounded px-2 py-1 text-xs text-gray-500 hover:bg-gray-200 dark:hover:bg-gray-800"
            :title="__('Rename this entry or change its icon')"
            @click.stop="editing = editing === index ? null : index"
          >
            {{ __('edit') }}
          </button>

          <button
            v-if="node.sortable"
            type="button"
            class="rounded px-2 py-1 text-xs text-gray-500 hover:bg-gray-200 dark:hover:bg-gray-800"
            :title="node.hidden
              ? __('Show this entry in the sidebar')
              : __('Hide this entry from the sidebar. It stays here so it can be brought back.')"
            @click.stop="node.hidden = !node.hidden"
          >
            {{ node.hidden ? __('show') : __('hide') }}
          </button>
        </div>
      </div>

      <div
        v-if="editing === index"
        class="mt-2 ml-6 rounded border border-gray-200 p-3 dark:border-gray-700"
      >
        <div class="flex flex-wrap items-end gap-4">
          <label class="flex flex-col gap-1">
            <span class="text-xs text-gray-500">{{ __('Label') }}</span>
            <input
              v-model="node.label_override"
              type="text"
              class="form-control form-input form-control-bordered"
              :placeholder="node.label"
            />
          </label>

          <label v-if="node.supports_icon" class="flex flex-col gap-1">
            <span class="text-xs text-gray-500">{{ __('Icon') }}</span>
            <select
              v-model="node.icon_override"
              class="form-control form-select form-control-bordered"
            >
              <option :value="null">
                {{ node.icon ? __('default (:icon)', { icon: node.icon }) : __('none') }}
              </option>
              <option v-for="icon in iconChoices(node)" :key="icon" :value="icon">
                {{ icon }}
              </option>
            </select>
          </label>

          <span v-else class="text-xs text-gray-400">
            {{ __('Only top level sections show an icon in Nova.') }}
          </span>

          <button
            type="button"
            class="rounded px-2 py-1 text-xs text-gray-500 hover:bg-gray-200 dark:hover:bg-gray-800"
            @click="node.label_override = null; node.icon_override = null"
          >
            {{ __('reset') }}
          </button>
        </div>

        <div class="mt-4 flex flex-col gap-1">
          <span class="text-xs text-gray-500">{{ __('Visible with') }}</span>

          <div class="flex flex-wrap items-center gap-2">
            <span
              v-for="permission in node.permissions"
              :key="permission"
              class="inline-flex items-center gap-1 rounded bg-gray-200 px-2 py-1 text-xs dark:bg-gray-800"
            >
              {{ permission }}
              <button type="button" class="text-gray-500" @click="removePermission(node, permission)">&times;</button>
            </span>

            <input
              v-model="permissionDraft"
              type="text"
              :list="listId"
              class="form-control form-input form-control-bordered"
              :placeholder="__('add a permission')"
              @keydown.enter.prevent="addPermission(node)"
              @blur="addPermission(node)"
            />

            <datalist :id="listId">
              <option v-for="permission in availablePermissions" :key="permission" :value="permission" />
            </datalist>
          </div>

          <span class="text-xs text-gray-400">
            {{ __('Empty means everyone sees it. Any one of the listed permissions is enough.') }}
            {{ __('This hides the link only — the page behind it is authorised separately by Nova.') }}
          </span>
        </div>
      </div>

      <MenuBranch v-if="node.children.length" :nodes="node.children" :depth="depth + 1" />
    </li>
  </ul>
</template>

<script>
import icons from '../icons';
import { isDescendant } from '../tree';

export default {
  name: 'MenuBranch',

  inject: ['drag', 'moveNode', 'availablePermissions'],

  props: {
    nodes: { type: Array, required: true },
    depth: { type: Number, default: 0 },
  },

  data() {
    return { editing: null, permissionDraft: '' };
  },

  computed: {
    listId() {
      return `nmw-permissions-${this.depth}-${this.editing}`;
    },
  },

  methods: {
    addPermission(node) {
      const value = this.permissionDraft.trim();

      if (value && !(node.permissions || []).includes(value)) {
        node.permissions = [...(node.permissions || []), value];
      }

      this.permissionDraft = '';
    },

    removePermission(node, permission) {
      node.permissions = node.permissions.filter(item => item !== permission);
    },

    /**
     * The names Nova can draw, plus whatever is already stored - an icon set
     * before a Nova upgrade should stay selectable rather than silently
     * vanish from the list.
     */
    iconChoices(node) {
      return node.icon_override && !icons.includes(node.icon_override)
        ? [node.icon_override, ...icons]
        : icons;
    },

    onDragStart(node, event) {
      if (!node.sortable) {
        return;
      }

      // Without this the ancestors start their own drag as well and the row
      // that ends up moving is not the one grabbed.
      event.stopPropagation();

      this.drag.node = node;
      event.dataTransfer.effectAllowed = 'move';
    },

    /**
     * Top and bottom of a row mean "put it next to this one"; the middle of a
     * container means "put it inside". Anything else the tree would refuse is
     * left without a hint, so nothing is highlighted as droppable.
     */
    onDragOver(node, event) {
      const dragged = this.drag.node;

      if (!dragged || dragged === node || isDescendant(dragged, node)) {
        return;
      }

      event.preventDefault();
      event.stopPropagation();

      const rect = event.currentTarget.getBoundingClientRect();
      const offset = (event.clientY - rect.top) / rect.height;

      this.drag.over = node;
      this.drag.mode =
        node.can_accept_children && offset > 0.35 && offset < 0.65
          ? 'inside'
          : offset < 0.5
            ? 'before'
            : 'after';
    },

    onDrop(node, event) {
      if (!this.drag.node || !this.drag.mode) {
        return;
      }

      event.preventDefault();
      event.stopPropagation();

      this.moveNode(this.drag.node, node, this.drag.mode);
      this.onDragEnd();
    },

    dropHint(node) {
      return this.drag.over === node ? this.drag.mode : null;
    },

    onDragEnd() {
      this.drag.node = null;
      this.drag.over = null;
      this.drag.mode = null;
    },
  },
};
</script>
