// Moving nodes around the tree the screen holds in memory. Nothing here talks
// to the server: the whole tree is sent on save and the backend decides what
// is worth storing.

export function isDescendant(node, candidate) {
  return (node.children || []).some(
    child => child === candidate || isDescendant(child, candidate)
  );
}

export function findList(nodes, node) {
  if (nodes.includes(node)) {
    return nodes;
  }

  for (const child of nodes) {
    const found = findList(child.children || [], node);

    if (found) {
      return found;
    }
  }

  return null;
}

export function detach(nodes, node) {
  const list = findList(nodes, node);

  if (list) {
    list.splice(list.indexOf(node), 1);
  }

  return list;
}

/**
 * Drop `node` relative to `target`: before it, after it, or as its last child.
 * Returns false when the move makes no sense, so the caller can leave the tree
 * untouched.
 */
export function move(nodes, node, target, mode) {
  if (node === target || isDescendant(node, target)) {
    return false;
  }

  if (mode === 'inside' && !target.can_accept_children) {
    return false;
  }

  detach(nodes, node);

  if (mode === 'inside') {
    target.children = target.children || [];
    target.children.push(node);

    return true;
  }

  const list = findList(nodes, target);

  if (!list) {
    return false;
  }

  list.splice(list.indexOf(target) + (mode === 'after' ? 1 : 0), 0, node);

  return true;
}

/**
 * Depth first, carrying the parent each entry ended up under. That is what the
 * backend compares against the menu's own structure.
 */
export function flatten(nodes, parent = null, into = []) {
  nodes.forEach(node => {
    const identity = node.id || null;

    if (identity) {
      into.push({
        id: identity,
        parent,
        hidden: !!node.hidden,
        label: node.label_override || null,
        icon: node.supports_icon ? node.icon_override || null : null,
        permissions: node.permissions || [],
      });
    }

    flatten(node.children || [], identity || parent, into);
  });

  return into;
}
