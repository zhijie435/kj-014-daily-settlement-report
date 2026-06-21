<template>
  <div class="min-h-screen bg-[#FDFDFC] dark:bg-[#0a0a0a] p-6 lg:p-8">
    <div class="max-w-6xl mx-auto">
      <div class="flex items-center justify-between mb-8">
        <div>
          <h1 class="text-2xl font-semibold text-[#1b1b18] dark:text-[#EDEDEC]">
            Customer Groups
          </h1>
          <p class="text-[#706f6c] dark:text-[#A1A09A] text-sm mt-1">
            Manage customer groups and configurations
          </p>
        </div>
        <button
          @click="openCreateModal"
          class="inline-flex items-center gap-2 px-5 py-2 bg-[#1b1b18] text-white rounded-sm border border-[#1b1b18] hover:bg-black transition-all dark:bg-[#eeeeec] dark:text-[#1C1C1A] dark:border-[#eeeeec] dark:hover:bg-white"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
          </svg>
          Add Group
        </button>
      </div>

      <div class="bg-white dark:bg-[#161615] shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d] rounded-sm overflow-hidden">
        <div class="p-4 border-b border-[#e3e3e0] dark:border-[#3E3E3A]">
          <div class="flex flex-col sm:flex-row gap-4">
            <div class="flex-1">
              <div class="relative">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-[#706f6c] dark:text-[#A1A09A]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="11" cy="11" r="8"></circle>
                  <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input
                  v-model="search"
                  type="text"
                  placeholder="Search groups..."
                  class="w-full pl-10 pr-4 py-2 bg-[#FDFDFC] dark:bg-[#0a0a0a] border border-[#e3e3e0] dark:border-[#3E3E3A] rounded-sm text-sm text-[#1b1b18] dark:text-[#EDEDEC] placeholder-[#706f6c] dark:placeholder-[#A1A09A] focus:outline-none focus:border-[#1b1b18] dark:focus:border-[#eeeeec]"
                  @input="debouncedSearch"
                />
              </div>
            </div>
            <div class="flex items-center gap-2">
              <label class="flex items-center gap-2 text-sm text-[#1b1b18] dark:text-[#EDEDEC]">
                <input
                  v-model="filterActive"
                  type="checkbox"
                  class="w-4 h-4 rounded border-[#e3e3e0] dark:border-[#3E3E3A] text-[#1b1b18] dark:text-[#eeeeec] focus:ring-0"
                  @change="fetchCustomerGroups"
                />
                Active only
              </label>
            </div>
          </div>
        </div>

        <div v-if="loading" class="p-12 text-center">
          <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-[#e3e3e0] dark:border-[#3E3E3A] border-t-[#1b1b18] dark:border-t-[#eeeeec]"></div>
          <p class="mt-4 text-[#706f6c] dark:text-[#A1A09A]">Loading...</p>
        </div>

        <div v-else-if="customerGroups.length === 0" class="p-12 text-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-16 h-16 mx-auto text-[#e3e3e0] dark:text-[#3E3E3A] mb-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
          </svg>
          <p class="text-[#706f6c] dark:text-[#A1A09A]">No customer groups found</p>
          <button
            @click="openCreateModal"
            class="mt-4 text-[#f53003] dark:text-[#FF4433] text-sm font-medium hover:underline"
          >
            Create your first group
          </button>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="border-b border-[#e3e3e0] dark:border-[#3E3E3A]">
                <th class="px-4 py-3 text-left text-xs font-medium text-[#706f6c] dark:text-[#A1A09A] uppercase tracking-wider">
                  Name
                </th>
                <th class="px-4 py-3 text-left text-xs font-medium text-[#706f6c] dark:text-[#A1A09A] uppercase tracking-wider">
                  Code
                </th>
                <th class="px-4 py-3 text-left text-xs font-medium text-[#706f6c] dark:text-[#A1A09A] uppercase tracking-wider">
                  Description
                </th>
                <th class="px-4 py-3 text-left text-xs font-medium text-[#706f6c] dark:text-[#A1A09A] uppercase tracking-wider">
                  Status
                </th>
                <th class="px-4 py-3 text-left text-xs font-medium text-[#706f6c] dark:text-[#A1A09A] uppercase tracking-wider">
                  Sort Order
                </th>
                <th class="px-4 py-3 text-right text-xs font-medium text-[#706f6c] dark:text-[#A1A09A] uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#e3e3e0] dark:divide-[#3E3E3A]">
              <tr
                v-for="group in customerGroups"
                :key="group.id"
                class="hover:bg-[#FDFDFC] dark:hover:bg-[#0a0a0a] transition-colors"
              >
                <td class="px-4 py-3">
                  <div class="text-sm font-medium text-[#1b1b18] dark:text-[#EDEDEC]">
                    {{ group.name }}
                  </div>
                </td>
                <td class="px-4 py-3">
                  <code class="text-xs bg-[#FDFDFC] dark:bg-[#0a0a0a] px-2 py-1 rounded text-[#706f6c] dark:text-[#A1A09A]">
                    {{ group.code }}
                  </code>
                </td>
                <td class="px-4 py-3">
                  <div class="text-sm text-[#706f6c] dark:text-[#A1A09A] max-w-xs truncate">
                    {{ group.description || '-' }}
                  </div>
                </td>
                <td class="px-4 py-3">
                  <span
                    :class="[
                      'inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium',
                      group.is_active
                        ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                        : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                    ]"
                  >
                    <span
                      :class="[
                        'w-1.5 h-1.5 rounded-full',
                        group.is_active ? 'bg-green-500' : 'bg-gray-400'
                      ]"
                    ></span>
                    {{ group.is_active ? 'Active' : 'Inactive' }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <span class="text-sm text-[#706f6c] dark:text-[#A1A09A]">
                    {{ group.sort_order }}
                  </span>
                </td>
                <td class="px-4 py-3 text-right">
                  <div class="flex items-center justify-end gap-2">
                    <button
                      @click="openEditModal(group)"
                      class="p-1.5 text-[#706f6c] dark:text-[#A1A09A] hover:text-[#1b1b18] dark:hover:text-[#EDEDEC] hover:bg-[#FDFDFC] dark:hover:bg-[#0a0a0a] rounded transition-colors"
                      title="Edit"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                      </svg>
                    </button>
                    <button
                      @click="confirmDelete(group)"
                      class="p-1.5 text-[#706f6c] dark:text-[#A1A09A] hover:text-[#f53003] dark:hover:text-[#FF4433] hover:bg-[#fff2f2] dark:hover:bg-[#1D0002] rounded transition-colors"
                      title="Delete"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        <line x1="10" y1="11" x2="10" y2="17"></line>
                        <line x1="14" y1="11" x2="14" y2="17"></line>
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="pagination && pagination.total > pagination.per_page" class="px-4 py-3 border-t border-[#e3e3e0] dark:border-[#3E3E3A] flex items-center justify-between">
          <div class="text-sm text-[#706f6c] dark:text-[#A1A09A]">
            Showing {{ (pagination.current_page - 1) * pagination.per_page + 1 }} to {{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }} of {{ pagination.total }} results
          </div>
          <div class="flex items-center gap-2">
            <button
              @click="changePage(pagination.current_page - 1)"
              :disabled="pagination.current_page <= 1"
              class="px-3 py-1 text-sm border border-[#e3e3e0] dark:border-[#3E3E3A] rounded-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-[#FDFDFC] dark:hover:bg-[#0a0a0a] text-[#1b1b18] dark:text-[#EDEDEC]"
            >
              Previous
            </button>
            <span class="text-sm text-[#706f6c] dark:text-[#A1A09A]">
              Page {{ pagination.current_page }} of {{ pagination.last_page }}
            </span>
            <button
              @click="changePage(pagination.current_page + 1)"
              :disabled="pagination.current_page >= pagination.last_page"
              class="px-3 py-1 text-sm border border-[#e3e3e0] dark:border-[#3E3E3A] rounded-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-[#FDFDFC] dark:hover:bg-[#0a0a0a] text-[#1b1b18] dark:text-[#EDEDEC]"
            >
              Next
            </button>
          </div>
        </div>
      </div>

      <div
        v-if="showModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
      >
        <div class="absolute inset-0 bg-black/50" @click="closeModal"></div>
        <div class="relative bg-white dark:bg-[#161615] w-full max-w-lg rounded-sm shadow-xl overflow-hidden">
          <div class="px-6 py-4 border-b border-[#e3e3e0] dark:border-[#3E3E3A]">
            <h2 class="text-lg font-semibold text-[#1b1b18] dark:text-[#EDEDEC]">
              {{ editingGroup ? 'Edit Customer Group' : 'Create Customer Group' }}
            </h2>
          </div>

          <form @submit.prevent="handleSubmit" class="p-6 space-y-4">
            <div>
              <label class="block text-sm font-medium text-[#1b1b18] dark:text-[#EDEDEC] mb-1">
                Name <span class="text-[#f53003] dark:text-[#FF4433]">*</span>
              </label>
              <input
                v-model="form.name"
                type="text"
                required
                class="w-full px-3 py-2 bg-[#FDFDFC] dark:bg-[#0a0a0a] border border-[#e3e3e0] dark:border-[#3E3E3A] rounded-sm text-sm text-[#1b1b18] dark:text-[#EDEDEC] focus:outline-none focus:border-[#1b1b18] dark:focus:border-[#eeeeec]"
                placeholder="Enter group name"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-[#1b1b18] dark:text-[#EDEDEC] mb-1">
                Code <span class="text-[#f53003] dark:text-[#FF4433]">*</span>
              </label>
              <input
                v-model="form.code"
                type="text"
                required
                :disabled="!!editingGroup"
                class="w-full px-3 py-2 bg-[#FDFDFC] dark:bg-[#0a0a0a] border border-[#e3e3e0] dark:border-[#3E3E3A] rounded-sm text-sm text-[#1b1b18] dark:text-[#EDEDEC] focus:outline-none focus:border-[#1b1b18] dark:focus:border-[#eeeeec] disabled:opacity-50 disabled:cursor-not-allowed"
                placeholder="e.g., VIP, NEW, LOYAL"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-[#1b1b18] dark:text-[#EDEDEC] mb-1">
                Description
              </label>
              <textarea
                v-model="form.description"
                rows="3"
                class="w-full px-3 py-2 bg-[#FDFDFC] dark:bg-[#0a0a0a] border border-[#e3e3e0] dark:border-[#3E3E3A] rounded-sm text-sm text-[#1b1b18] dark:text-[#EDEDEC] focus:outline-none focus:border-[#1b1b18] dark:focus:border-[#eeeeec] resize-none"
                placeholder="Enter group description"
              ></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-[#1b1b18] dark:text-[#EDEDEC] mb-1">
                  Sort Order
                </label>
                <input
                  v-model.number="form.sort_order"
                  type="number"
                  min="0"
                  class="w-full px-3 py-2 bg-[#FDFDFC] dark:bg-[#0a0a0a] border border-[#e3e3e0] dark:border-[#3E3E3A] rounded-sm text-sm text-[#1b1b18] dark:text-[#EDEDEC] focus:outline-none focus:border-[#1b1b18] dark:focus:border-[#eeeeec]"
                />
              </div>
              <div class="flex items-end">
                <label class="flex items-center gap-2 text-sm text-[#1b1b18] dark:text-[#EDEDEC] cursor-pointer">
                  <input
                    v-model="form.is_active"
                    type="checkbox"
                    class="w-4 h-4 rounded border-[#e3e3e0] dark:border-[#3E3E3A] text-[#1b1b18] dark:text-[#eeeeec] focus:ring-0"
                  />
                  Active
                </label>
              </div>
            </div>

            <div class="pt-4 flex items-center justify-end gap-3">
              <button
                type="button"
                @click="closeModal"
                class="px-4 py-2 text-sm border border-[#e3e3e0] dark:border-[#3E3E3A] rounded-sm hover:bg-[#FDFDFC] dark:hover:bg-[#0a0a0a] text-[#1b1b18] dark:text-[#EDEDEC]"
              >
                Cancel
              </button>
              <button
                type="submit"
                :disabled="submitting"
                class="px-4 py-2 text-sm bg-[#1b1b18] text-white rounded-sm border border-[#1b1b18] hover:bg-black transition-all disabled:opacity-50 disabled:cursor-not-allowed dark:bg-[#eeeeec] dark:text-[#1C1C1A] dark:border-[#eeeeec] dark:hover:bg-white"
              >
                <span v-if="submitting">Saving...</span>
                <span v-else>{{ editingGroup ? 'Update' : 'Create' }}</span>
              </button>
            </div>
          </form>
        </div>
      </div>

      <div
        v-if="showDeleteModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
      >
        <div class="absolute inset-0 bg-black/50" @click="showDeleteModal = false"></div>
        <div class="relative bg-white dark:bg-[#161615] w-full max-w-md rounded-sm shadow-xl overflow-hidden">
          <div class="p-6">
            <div class="flex items-center gap-4">
              <div class="flex-shrink-0 w-12 h-12 rounded-full bg-[#fff2f2] dark:bg-[#1D0002] flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-[#f53003] dark:text-[#FF4433]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <polygon points="7.86 2 16.14 2 22 7.86 22 16.14 16.14 22 7.86 22 2 16.14 2 7.86 7.86 2"></polygon>
                  <line x1="12" y1="8" x2="12" y2="12"></line>
                  <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
              </div>
              <div>
                <h3 class="text-lg font-semibold text-[#1b1b18] dark:text-[#EDEDEC]">
                  Delete Customer Group
                </h3>
                <p class="text-sm text-[#706f6c] dark:text-[#A1A09A] mt-1">
                  Are you sure you want to delete <span class="font-medium text-[#1b1b18] dark:text-[#EDEDEC]">"{{ deletingGroup?.name }}"</span>? This action cannot be undone.
                </p>
              </div>
            </div>
            <div class="pt-6 flex items-center justify-end gap-3">
              <button
                @click="showDeleteModal = false"
                class="px-4 py-2 text-sm border border-[#e3e3e0] dark:border-[#3E3E3A] rounded-sm hover:bg-[#FDFDFC] dark:hover:bg-[#0a0a0a] text-[#1b1b18] dark:text-[#EDEDEC]"
              >
                Cancel
              </button>
              <button
                @click="handleDelete"
                :disabled="deleting"
                class="px-4 py-2 text-sm bg-[#f53003] text-white rounded-sm border border-[#f53003] hover:bg-[#d62a00] transition-all disabled:opacity-50 disabled:cursor-not-allowed dark:bg-[#FF4433] dark:border-[#FF4433]"
              >
                <span v-if="deleting">Deleting...</span>
                <span v-else>Delete</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <div
        v-if="toast.show"
        :class="[
          'fixed bottom-6 right-6 z-50 px-4 py-3 rounded-sm shadow-lg flex items-center gap-3 transition-all transform',
          toast.type === 'success' ? 'bg-green-600 text-white' : 'bg-[#f53003] text-white'
        ]"
      >
        <svg v-if="toast.type === 'success'" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="20 6 9 17 4 12"></polyline>
        </svg>
        <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="15" y1="9" x2="9" y2="15"></line>
          <line x1="9" y1="9" x2="15" y2="15"></line>
        </svg>
        <span>{{ toast.message }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const customerGroups = ref([]);
const loading = ref(false);
const search = ref('');
const filterActive = ref(false);
const currentPage = ref(1);
const pagination = ref(null);

const showModal = ref(false);
const editingGroup = ref(null);
const submitting = ref(false);

const showDeleteModal = ref(false);
const deletingGroup = ref(null);
const deleting = ref(false);

const toast = ref({
  show: false,
  message: '',
  type: 'success'
});

const form = ref({
  name: '',
  code: '',
  description: '',
  is_active: true,
  sort_order: 0
});

let searchTimeout = null;

const fetchCustomerGroups = async () => {
  loading.value = true;
  try {
    const params = {
      page: currentPage.value,
      per_page: 15
    };

    if (search.value) {
      params.search = search.value;
    }

    if (filterActive.value) {
      params.active = 1;
    }

    const response = await axios.get('/api/customer-groups', { params });
    customerGroups.value = response.data.data;
    pagination.value = response.data.pagination;
  } catch (error) {
    console.error('Error fetching customer groups:', error);
    showToast('Failed to load customer groups', 'error');
  } finally {
    loading.value = false;
  }
};

const debouncedSearch = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    currentPage.value = 1;
    fetchCustomerGroups();
  }, 300);
};

const changePage = (page) => {
  currentPage.value = page;
  fetchCustomerGroups();
};

const resetForm = () => {
  form.value = {
    name: '',
    code: '',
    description: '',
    is_active: true,
    sort_order: 0
  };
  editingGroup.value = null;
};

const openCreateModal = () => {
  resetForm();
  showModal.value = true;
};

const openEditModal = (group) => {
  editingGroup.value = group;
  form.value = {
    name: group.name,
    code: group.code,
    description: group.description || '',
    is_active: group.is_active,
    sort_order: group.sort_order
  };
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
  resetForm();
};

const handleSubmit = async () => {
  submitting.value = true;
  try {
    if (editingGroup.value) {
      await axios.put(`/api/customer-groups/${editingGroup.value.id}`, form.value);
      showToast('Customer group updated successfully', 'success');
    } else {
      await axios.post('/api/customer-groups', form.value);
      showToast('Customer group created successfully', 'success');
    }
    closeModal();
    fetchCustomerGroups();
  } catch (error) {
    console.error('Error saving customer group:', error);
    const message = error.response?.data?.message || 'Failed to save customer group';
    showToast(message, 'error');
  } finally {
    submitting.value = false;
  }
};

const confirmDelete = (group) => {
  deletingGroup.value = group;
  showDeleteModal.value = true;
};

const handleDelete = async () => {
  if (!deletingGroup.value) return;

  deleting.value = true;
  try {
    await axios.delete(`/api/customer-groups/${deletingGroup.value.id}`);
    showToast('Customer group deleted successfully', 'success');
    showDeleteModal.value = false;
    deletingGroup.value = null;
    fetchCustomerGroups();
  } catch (error) {
    console.error('Error deleting customer group:', error);
    showToast('Failed to delete customer group', 'error');
  } finally {
    deleting.value = false;
  }
};

const showToast = (message, type = 'success') => {
  toast.value = {
    show: true,
    message,
    type
  };

  setTimeout(() => {
    toast.value.show = false;
  }, 3000);
};

onMounted(() => {
  fetchCustomerGroups();
});
</script>
