<template>
  <LoadingView :loading="loading">
    <Head :title="__('Menu Order')" />

    <div class="flex items-center justify-between mb-6">
      <Heading>{{ __('Menu Order') }}</Heading>

      <button
        type="button"
        class="shadow rounded focus:outline-none focus:ring ring-primary-200 bg-primary-500 hover:bg-primary-400 active:bg-primary-600 text-white dark:text-gray-900 inline-flex items-center justify-center h-9 px-3 text-sm font-bold disabled:opacity-50"
        :disabled="saving || !tree.length"
        @click="save"
      >
        {{ saving ? __('Saving...') : __('Save') }}
      </button>
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
      availablePermissions: computed(() => this.permissions),
    };
  },

  data() {
    return {
      loading: true,
      saving: false,
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
