import { reactive, computed } from 'vue'

export interface ElementConfig {
  label: string
  placeholder: string
  fieldPath: string
  fieldType: string
  terminalDoctype: string
  editable: boolean
  frameColor: string
}

export interface BuilderElement {
  id: string
  type: 'field' | 'caption'
  x: number
  y: number
  w: number
  h: number
  config: ElementConfig
}

export interface GridConfig {
  columns: number
  rowHeight: number
  gap: number
}

export interface BuilderState {
  formName: string
  title: string
  targetDoctype: string
  gridConfig: GridConfig
  elements: BuilderElement[]
  selectedId: string | null
}

export function useBuilderState(formName: string) {
  const state = reactive<BuilderState>({
    formName,
    title: '',
    targetDoctype: '',
    gridConfig: {
      columns: 12,
      rowHeight: 48,
      gap: 4
    },
    elements: [],
    selectedId: null
  })

  const selectedElement = computed<BuilderElement | null>(() => {
    return state.elements.find(el => el.id === state.selectedId) ?? null
  })

  function addElement(type: 'field' | 'caption', x: number, y: number): void {
    const id = `el_${Date.now()}`
    const element: BuilderElement = {
      id,
      type,
      x,
      y,
      w: 3,
      h: 1,
      config: {
        label: type === 'field' ? 'New Field' : 'Caption',
        placeholder: '',
        fieldPath: '',
        fieldType: '',
        terminalDoctype: '',
        editable: true,
        frameColor: ''
      }
    }
    state.elements.push(element)
    state.selectedId = id
  }

  function removeElement(id: string): void {
    const index = state.elements.findIndex(el => el.id === id)
    if (index !== -1) {
      state.elements.splice(index, 1)
      if (state.selectedId === id) {
        state.selectedId = null
      }
    }
  }

  function updateElement(id: string, changes: Partial<ElementConfig>): void {
    const element = state.elements.find(el => el.id === id)
    if (element) {
      Object.assign(element.config, changes)
    }
  }

  function moveElement(id: string, x: number, y: number): void {
    const element = state.elements.find(el => el.id === id)
    if (element) {
      element.x = Math.max(0, x)
      element.y = Math.max(0, y)
      element.x = Math.min(element.x, state.gridConfig.columns - element.w)
    }
  }

  function resizeElement(id: string, w: number, h: number): void {
    const element = state.elements.find(el => el.id === id)
    if (element) {
      element.w = Math.max(1, w)
      element.h = Math.max(1, h)
      element.x = Math.min(element.x, state.gridConfig.columns - element.w)
    }
  }

  function selectElement(id: string | null): void {
    state.selectedId = id
  }

  function serialise(): { grid_layout: string; grid_config: string } {
    return {
      grid_layout: JSON.stringify(state.elements),
      grid_config: JSON.stringify(state.gridConfig)
    }
  }

  async function save(): Promise<void> {
    const { grid_layout, grid_config } = serialise()

    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      'X-Frappe-CSRF-Token': (window as any).csrf_token
    }

    await fetch('/api/method/frappe.client.set_value', {
      method: 'POST',
      headers,
      credentials: 'include',
      body: JSON.stringify({
        doctype: 'NCE Form Definition',
        name: state.formName,
        fieldname: 'grid_layout',
        value: grid_layout
      })
    })

    await fetch('/api/method/frappe.client.set_value', {
      method: 'POST',
      headers,
      credentials: 'include',
      body: JSON.stringify({
        doctype: 'NCE Form Definition',
        name: state.formName,
        fieldname: 'grid_config',
        value: grid_config
      })
    })
  }

  async function load(): Promise<void> {
    try {
      const response = await fetch(`/api/resource/NCE Form Definition/${state.formName}`, {
        credentials: 'include'
      })
      if (!response.ok) return

      const data = await response.json()
      const resource = data.data

      state.title = resource.title || ''
      state.targetDoctype = resource.target_doctype || ''

      try {
        state.elements = resource.grid_layout ? JSON.parse(resource.grid_layout) : []
      } catch {
        state.elements = []
      }

      try {
        if (resource.grid_config) {
          state.gridConfig = JSON.parse(resource.grid_config)
        }
      } catch {
        state.gridConfig = { columns: 12, rowHeight: 48, gap: 4 }
      }
    } catch {
      // fallback to defaults
    }
  }

  return {
    state,
    selectedElement,
    addElement,
    removeElement,
    updateElement,
    moveElement,
    resizeElement,
    selectElement,
    serialise,
    save,
    load
  }
}
