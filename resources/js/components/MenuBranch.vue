<template>
  <ul :class="depth === 0 ? 'space-y-2' : 'mt-2 space-y-1 pl-6 border-l border-gray-200 dark:border-gray-700'">
    <template v-for="(node, index) in nodes" :key="node.id || `${node.label}-${index}`">
      <li
        v-show="dragging"
        class="rounded transition-colors"
        :class="betweenHover === index ? 'h-8 bg-primary-100 dark:bg-primary-900/40 ring-2 ring-primary-500' : 'h-2'"
        @dragenter.prevent.stop="betweenHover = index"
        @dragover.prevent.stop="betweenHover = index"
        @dragleave="onBetweenLeave($event)"
        @drop.prevent.stop="dropBetween(index)"
      />

      <li>
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
            v-if="node.empty"
            class="shrink-0 text-xs text-yellow-600 dark:text-yellow-500"
            :title="__('Nova does not draw a container with nothing in it. Drag something inside and it will appear.')"
          >
            {{ __('empty — not shown in the menu') }}
          </span>
          <span
            v-if="node.custom"
            class="shrink-0 rounded bg-primary-100 px-2 py-0.5 text-xs text-primary-600 dark:bg-primary-900/40 dark:text-primary-300"
            :title="__('Added here, not by the application')"
          >
            {{ __('custom') }}
          </span>
          <span
            v-if="node.moved"
            class="shrink-0 text-xs text-yellow-600 dark:text-yellow-500"
            :title="__('Moved out of the section the menu itself puts it in.')"
          >
            {{ __('moved') }}
          </span>
          <!--
            An entry added here has no original to compare against: its row
            holds its only name, so printing it in brackets says it twice.
          -->
          <span
            v-if="node.label_override && ! node.custom"
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
            v-if="node.custom"
            type="button"
            class="rounded px-2 py-1 text-xs text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20"
            :title="__('Delete this entry. Entries the application provides can only be hidden.')"
            @click.stop="removing = index"
          >
            {{ __('delete') }}
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
        v-if="removing === index"
        class="mt-2 ml-6 flex items-center gap-3 rounded border border-red-200 p-3 text-sm dark:border-red-900"
      >
        <span class="text-gray-500">
          {{ __('Delete ":name"? Anything moved into it goes back where it was.', { name: node.label_override || node.label }) }}
        </span>
        <button type="button" class="rounded px-2 py-1 text-xs text-gray-500 hover:bg-gray-200 dark:hover:bg-gray-800" @click="removing = null">
          {{ __('cancel') }}
        </button>
        <button
          type="button"
          class="rounded bg-red-500 px-2 py-1 text-xs font-bold text-white hover:bg-red-400"
          @click="removing = null; deleteCustom(node)"
        >
          {{ __('delete') }}
        </button>
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

          <label v-if="node.custom && node.type === 'item'" class="flex flex-col gap-1">
            <span class="text-xs text-gray-500">{{ __('Path or URL') }}</span>
            <input v-model="node.path" type="text" class="form-control form-input form-control-bordered" />
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

      <div
        v-show="canDropInside(node)"
        class="ml-6 mt-2 rounded border-2 border-dashed p-3 text-center text-xs"
        :class="insideHover === index
          ? 'border-primary-500 text-primary-500'
          : 'border-gray-300 text-gray-400 dark:border-gray-700'"
        @dragenter.prevent.stop="insideHover = index"
        @dragover.prevent.stop="insideHover = index"
        @dragleave="onZoneLeave($event)"
        @drop.prevent.stop="dropInside(node)"
      >
        {{ __('Drop here to put it inside ":name"', { name: node.label_override || node.label }) }}
      </div>
      </li>
    </template>

    <li
      v-show="dragging && nodes.length"
      class="rounded transition-colors"
      :class="betweenHover === nodes.length ? 'h-8 bg-primary-100 dark:bg-primary-900/40 ring-2 ring-primary-500' : 'h-2'"
      @dragenter.prevent.stop="betweenHover = nodes.length"
      @dragover.prevent.stop="betweenHover = nodes.length"
      @dragleave="onBetweenLeave($event)"
      @drop.prevent.stop="dropBetween(nodes.length)"
    />
  </ul>
</template>

<script>
import icons from '../icons';
import { isDescendant } from '../tree';

export default {
  name: 'MenuBranch',

  inject: ['drag', 'moveNode', 'deleteCustom', 'availablePermissions'],

  props: {
    nodes: { type: Array, required: true },
    depth: { type: Number, default: 0 },
  },

  data() {
    return { editing: null, removing: null, insideHover: null, betweenHover: null, dragTimer: null, permissionDraft: '' };
  },

  computed: {
    dragging() {
      return !! this.drag.node;
    },

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

    /**
     * Aiming at the middle of a row is fiddly, and an empty container has no
     * children to aim between at all - so each container grows an explicit
     * target while a drag is in progress.
     */
    canDropInside(node) {
      const dragged = this.drag.node;

      return (
        !!dragged &&
        node.can_accept_children &&
        dragged !== node &&
        !isDescendant(dragged, node)
      );
    },

    onBetweenLeave(event) {
      if (! event.currentTarget.contains(event.relatedTarget)) {
        this.betweenHover = null;
      }
    },

    /**
     * Drop on the strip before entry `index`, or on the trailing one to land
     * last. Aiming at the edge of a row still works; this is the target that
     * cannot be missed.
     */
    dropBetween(index) {
      const dragged = this.drag.node;

      this.betweenHover = null;

      if (dragged) {
        const last = index >= this.nodes.length;
        const reference = last ? this.nodes[this.nodes.length - 1] : this.nodes[index];

        if (reference && reference !== dragged) {
          this.moveNode(dragged, reference, last ? 'after' : 'before');
        }
      }

      this.onDragEnd();
    },

    onZoneLeave(event) {
      // Moving onto the zone's own text fires dragleave too; only a pointer
      // that actually left the box should put the highlight out.
      if (! event.currentTarget.contains(event.relatedTarget)) {
        this.insideHover = null;
      }
    },

    dropInside(node) {
      const dragged = this.drag.node;

      this.insideHover = null;

      if (dragged && this.canDropInside(node)) {
        this.moveNode(dragged, node, 'inside');
      }

      this.onDragEnd();
    },

    onDragStart(node, event) {
      if (!node.sortable) {
        return;
      }

      // Without this the ancestors start their own drag as well and the row
      // that ends up moving is not the one grabbed.
      event.stopPropagation();

      event.dataTransfer.effectAllowed = 'move';

      // A drag with nothing on the clipboard is cancelled the moment it
      // starts: Chrome fires dragstart and then dragend, with no dragover in
      // between. The payload itself is never read - the tree is moved through
      // the shared state - but it has to be there.
      event.dataTransfer.setData('text/plain', node.id || node.label);

      // Chrome drops a drag whose source is patched from inside dragstart:
      // it fires dragstart, then dragend at once, with no dragover between.
      // Publishing the state a tick later lets the drag engage first.
      this.dragTimer = setTimeout(() => {
          this.dragTimer = null;
          this.drag.node = node;
      }, 0);
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
      // The drag may end before the deferred state was ever published.
      if (this.dragTimer) {
        clearTimeout(this.dragTimer);
        this.dragTimer = null;
      }

      this.insideHover = null;
      this.betweenHover = null;
      this.drag.node = null;
      this.drag.over = null;
      this.drag.mode = null;
    },
  },
};
</script>
