<template>
  <LoadingView :loading="loading">
    <Head :title="__('Menu Order')" />

    <div class="flex items-center justify-between mb-6">
      <Heading>{{ __('Menu Order') }}</Heading>

      <div class="flex items-center gap-3">
        <button
          type="button"
          class="rounded focus:outline-none focus:ring ring-primary-200 text-primary-500 hover:bg-primary-50 dark:hover:bg-primary-900/20 inline-flex items-center justify-center h-9 px-3 text-sm font-bold disabled:opacity-50"
          :disabled="saving || resetting"
          @click="openCreate"
        >
          {{ __('Add entry') }}
        </button>

        <button
          type="button"
          class="rounded focus:outline-none focus:ring ring-red-200 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 inline-flex items-center justify-center h-9 px-3 text-sm font-bold disabled:opacity-50"
          :disabled="saving || resetting"
          @click="confirming = true"
        >
          {{ __('Reset') }}
        </button>

        <button
          type="button"
          class="shadow rounded focus:outline-none focus:ring ring-primary-200 bg-primary-500 hover:bg-primary-400 active:bg-primary-600 text-white dark:text-gray-900 inline-flex items-center justify-center h-9 px-3 text-sm font-bold disabled:opacity-50"
          :disabled="saving || resetting || !tree.length"
          @click="save"
        >
          {{ saving ? __('Saving...') : __('Save') }}
        </button>
      </div>
    </div>

    <div
      v-if="creating"
      class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 px-4"
      @click.self="creating = false"
    >
      <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-lg dark:bg-gray-800">
        <h3 class="mb-4 text-lg font-bold">{{ __('Add a menu entry') }}</h3>

        <div class="mb-4 flex flex-col gap-3">
          <label class="flex flex-col gap-1">
            <span class="text-xs text-gray-500">{{ __('Type') }}</span>
            <select v-model="draft.type" class="form-control form-select form-control-bordered">
              <option value="section">{{ __('Section — holds other entries') }}</option>
              <option value="item">{{ __('Link') }}</option>
            </select>
            <span v-if="draft.type === 'section'" class="text-xs text-gray-400">
              {{ __('Drawn as a heading at the top level and as a sub-heading once nested. It stays out of the menu until something is inside it.') }}
            </span>
          </label>

          <label class="flex flex-col gap-1">
            <span class="text-xs text-gray-500">{{ __('Label') }}</span>
            <input v-model="draft.label" type="text" class="form-control form-input form-control-bordered" />
          </label>

          <label v-if="draft.type === 'item'" class="flex flex-col gap-1">
            <span class="text-xs text-gray-500">{{ __('Path or URL') }}</span>
            <input
              v-model="draft.path"
              type="text"
              class="form-control form-input form-control-bordered"
              placeholder="/resources/posts"
            />
            <span class="text-xs text-gray-400">
              {{ __('Anything starting with http:// or https:// opens as an external link.') }}
            </span>
          </label>

          <label v-if="draft.type === 'section'" class="flex flex-col gap-1">
            <span class="text-xs text-gray-500">{{ __('Icon') }}</span>
            <input v-model="draft.icon" type="text" class="form-control form-input form-control-bordered" placeholder="bars-3" />
          </label>
        </div>

        <p v-if="createError" class="mb-4 text-sm text-red-500">{{ createError }}</p>

        <div class="flex justify-end gap-3">
          <button type="button" class="rounded h-9 px-3 text-sm font-bold text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-900" @click="creating = false">
            {{ __('Cancel') }}
          </button>
          <button
            type="button"
            class="shadow rounded bg-primary-500 hover:bg-primary-400 text-white dark:text-gray-900 inline-flex items-center justify-center h-9 px-3 text-sm font-bold disabled:opacity-50"
            :disabled="!draft.label || saving"
            @click="createEntry"
          >
            {{ __('Add') }}
          </button>
        </div>
      </div>
    </div>

    <div
      v-if="confirming"
      class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 px-4"
      @click.self="confirming = false"
      @keydown.esc="confirming = false"
    >
      <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-lg dark:bg-gray-800">
        <h3 class="mb-2 text-lg font-bold">{{ __('Reset the menu?') }}</h3>

        <p class="mb-6 text-gray-500">
          {{ __('The menu goes back to the default one: the order the tools register in, their own labels, icons and nesting.') }}
          {{ __('Every override is deleted — the order, the moves, renamed entries, icons, hidden entries and permissions. This cannot be undone.') }}
        </p>

        <div class="flex justify-end gap-3">
          <button
            type="button"
            class="rounded h-9 px-3 text-sm font-bold text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-900"
            :disabled="resetting"
            @click="confirming = false"
          >
            {{ __('Cancel') }}
          </button>

          <button
            type="button"
            class="shadow rounded bg-red-500 hover:bg-red-400 active:bg-red-600 text-white inline-flex items-center justify-center h-9 px-3 text-sm font-bold disabled:opacity-50"
            :disabled="resetting"
            @click="reset"
          >
            {{ resetting ? __('Resetting...') : __('Delete everything') }}
          </button>
        </div>
      </div>
    </div>

    <Card class="p-6">
      <p class="text-gray-500 mb-4">
        {{ __('Drag entries to reorder them. Drop on the top or bottom edge of a row to place an entry next to it, or on the middle of a section to put it inside.') }}
        {{ __('Hidden entries stay listed here, struck through, so they can be brought back.') }}
        {{ __('Use edit to rename an entry or change the icon of a top level section.') }}
      </p>

      <p class="text-gray-500 mb-4">
        {{ __('A section dropped inside another one is drawn as a sub-menu heading, since Nova styles sections for the top level only. Its icon is not shown there.') }}
      </p>

      <p class="text-gray-500 mb-4">
        {{ __('Moving an entry into another section changes who can see it: an entry shows only when every section above it is visible to that user. The page behind it stays authorised either way.') }}
      </p>

      <div v-if="!tree.length" class="text-gray-500">
        {{ __('Nothing in the menu can be reordered yet.') }}
      </div>

      <MenuBranch v-else :nodes="tree" :depth="0" />

      <div
        v-if="drag.node"
        class="mt-3 rounded border-2 border-dashed p-4 text-center text-sm"
        :class="overRoot
          ? 'border-primary-500 text-primary-500'
          : 'border-gray-300 text-gray-500 dark:border-gray-700'"
        @dragover.prevent="overRoot = true"
        @dragleave="overRoot = false"
        @drop.prevent="dropOnRoot"
      >
        {{ __('Drop here to move to the top level') }}
      </div>
    </Card>
  </LoadingView>
</template>

<script>
import { computed } from 'vue';
import MenuBranch from '../components/MenuBranch';
import { detach, flatten, move } from '../tree';

export default {
  components: { MenuBranch },

  provide() {
    return {
      drag: this.drag,
      moveNode: this.moveNode,
      deleteCustom: this.deleteCustom,
      availablePermissions: computed(() => this.permissions),
    };
  },

  data() {
    return {
      loading: true,
      saving: false,
      resetting: false,
      confirming: false,
      creating: false,
      createError: null,
      draft: { type: 'section', label: '', path: '', icon: '' },
      tree: [],
      permissions: [],
      // Shared by every branch: one drag crosses levels, so the state cannot
      // live in the branch that started it.
      drag: { node: null, over: null, mode: null },
      overRoot: false,
    };
  },

  created() {
    this.fetchTree();
  },

  methods: {
    async fetchTree() {
      this.loading = true;

      try {
        const { data } = await Nova.request().get('/nova-vendor/nova-menu-weights/entries');
        this.tree = data.tree;
        this.permissions = data.permissions || [];
      } catch (error) {
        Nova.error(this.__('Could not load the menu.'));
      }

      this.loading = false;
    },

    moveNode(node, target, mode) {
      move(this.tree, node, target, mode);
    },

    /**
     * Getting an entry back out to the top level by aiming at the edge of a
     * row is fiddly - every root row is a section, so most of its height means
     * "inside". This is the way out that always works.
     */
    dropOnRoot() {
      if (this.drag.node) {
        detach(this.tree, this.drag.node);
        this.tree.push(this.drag.node);
      }

      this.overRoot = false;
      this.drag.node = null;
      this.drag.over = null;
      this.drag.mode = null;
    },

    openCreate() {
      this.draft = { type: 'section', label: '', path: '', icon: '' };
      this.createError = null;
      this.creating = true;
    },

    async createEntry() {
      this.createError = null;

      try {
        await Nova.request().post('/nova-vendor/nova-menu-weights/custom', this.draft);

        this.creating = false;
        await this.fetchTree();
        Nova.success(this.__('Entry added. Save to place it where you want it.'));
      } catch (error) {
        this.createError =
          error.response?.data?.message || this.__('Could not add the entry.');
      }
    },

    async deleteCustom(node) {
      const id = node.id.replace('menu_custom:', '');

      try {
        await Nova.request().delete(`/nova-vendor/nova-menu-weights/custom/${id}`);
        await this.fetchTree();
      } catch (error) {
        Nova.error(this.__('Could not delete the entry.'));
      }
    },

    async reset() {
      this.resetting = true;

      try {
        await Nova.request().delete('/nova-vendor/nova-menu-weights/entries');

        // The sidebar is rendered server side, so the menu only goes back to
        // its defaults on the next page load.
        window.location.reload();
      } catch (error) {
        Nova.error(this.__('Could not reset the menu.'));
        this.resetting = false;
        this.confirming = false;
      }
    },

    async save() {
      this.saving = true;

      try {
        await Nova.request().post('/nova-vendor/nova-menu-weights/entries', {
          entries: flatten(this.tree),
        });

        Nova.success(this.__('Menu order saved. Reload to see the new sidebar.'));
      } catch (error) {
        Nova.error(this.__('Could not save the menu order.'));
      }

      this.saving = false;
    },
  },
};
</script>
