<script setup>
import { ref, onMounted, computed } from 'vue'

const folders = ref([])
const files = ref([])
const currentFolder = ref('')

// Fetch folder data
const fetchFolder = async (folder = '') => {
  try {
    const res = await fetch(`/api/folder?folder=${encodeURIComponent(folder)}`)
    const data = await res.json()
    if (data.success) {
      folders.value = data.folders
      files.value = data.files
      currentFolder.value = data.currentFolder
    }
  } catch (err) {
    console.error(err)
  }
}

// Navigate to folder
const goToFolder = (folder) => {
  fetchFolder(folder)
}

// Open file in a new tab (works for image or PDF)
const openFile = (file) => {
  // Direct path-based URL (no encoding)
  const url = `/file/stream/${file}`
  window.open(url, '_blank')
}

// Helpers
const getFolderName = (path) => path.split('/').pop()
const getFileName = (path) => path.split('/').pop()
const breadcrumbParts = computed(() => currentFolder.value.split('/').filter(Boolean))
const getPathUntil = (index) => breadcrumbParts.value.slice(0, index + 1).join('/')

onMounted(() => fetchFolder())
</script>

<template>
  <div class="app-file-explorer p-3">

    <div class="card shadow-sm">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">File Explorer</h5>
      </div>

      <div class="card-body">

        <!-- Breadcrumbs -->
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb mb-3">
            <li class="breadcrumb-item">
              <a href="#" @click.prevent="goToFolder('')">Root</a>
            </li>
            <li
              v-for="(part, index) in breadcrumbParts"
              :key="index"
              class="breadcrumb-item"
            >
              <a href="#" @click.prevent="goToFolder(getPathUntil(index))">{{ part }}</a>
            </li>
          </ol>
        </nav>

        <!-- Folders -->
        <div class="mb-4">
          <div class="row">
            <div v-for="folder in folders" :key="folder" class="col-auto mb-2">
              <div class="card text-center folder-card shadow-sm" @click="goToFolder(folder)">
                <div class="card-body p-2">
                  <i class="fal fa-folder fa-2x text-warning"></i>
                  <p class="mb-0 small">{{ getFolderName(folder) }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Files -->
        <div>
          <div class="row">
            <div v-for="file in files" :key="file" class="col-auto mb-2">
              <div class="card text-center file-card shadow-sm" @click="openFile(file)">
                <div class="card-body p-2">
                  <i class="fal fa-file fa-2x text-primary"></i>
                  <p class="mb-0 small">{{ getFileName(file) }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

  </div>
</template>

<style scoped>
.folder-card, .file-card {
  width: 100px;
  height: 100px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  transition: transform 0.2s;
  cursor: pointer;
}
.folder-card:hover, .file-card:hover {
  transform: scale(1.05);
}
</style>
