<template>
  <AppLayout>
    <!-- Page header -->
    <div class="page-header">
      <div>
        <nav aria-label="Breadcrumb" class="page-breadcrumb">
          <template v-if="backLink">
            <a :href="backLink.url">{{ backLink.label }}</a>
            <span class="sep">/</span>
          </template>
          <a href="/companies">Companies</a>
          <span class="sep">/</span>
          <span class="cur" aria-current="page">{{ company.name }}</span>
        </nav>
        <h1 class="page-title mt-1">{{ company.name }}</h1>
      </div>
      <div class="flex items-center gap-2">
        <button type="button" @click="showCompanyFilterModal" class="btn btn-secondary btn-sm">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="5.6" y1="5.6" x2="18.4" y2="18.4"/></svg>
          Filter
        </button>
      </div>
    </div>

    <!-- Merged warning -->
    <div v-if="company.merged_into_id" class="alert-warning mb-4 flex items-center gap-3">
      <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="12" stroke-width="1.75" stroke-linecap="round"/><circle cx="12" cy="16" r="0.75" fill="currentColor" stroke="none"/></svg>
      <span>This company has been merged into <a :href="`/companies/${company.merged_into.id}`" class="font-semibold underline hover:no-underline">{{ company.merged_into.name }}</a>. Data shown here belongs to the merged record only.</span>
    </div>

    <!-- MAIN GRID -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">

      <!-- LEFT COLUMN -->
      <div class="space-y-4">

        <!-- Company Card -->
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
          <!-- Dark header -->
          <div class="bg-gradient-to-b from-[#1c2028] to-[#252d3b] px-5 pt-5 pb-10">
            <div class="flex items-baseline gap-2 flex-wrap">
              <h2 class="text-white font-bold text-xl leading-tight">
                {{ primaryAlias?.alias ?? company.name }}
              </h2>
              <button v-if="nonPrimaryAliasCount > 0" @click="popup = 'aliases'"
                      class="text-xs text-blue-300 hover:text-blue-200 font-medium transition cursor-pointer">
                [+{{ nonPrimaryAliasCount }} more]
              </button>
              <button v-else @click="popup = 'aliases'"
                      class="text-xs text-gray-500 hover:text-gray-400 transition cursor-pointer">[manage]</button>
            </div>
            <p v-if="primaryAlias && primaryAlias.alias !== company.name"
               class="text-gray-400 text-xs mt-0.5 italic">{{ company.name }}</p>
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 mt-1.5">
              <span v-if="primaryDomain" class="text-gray-300 text-sm font-mono">{{ primaryDomain.domain }}</span>
              <button v-if="otherDomainCount > 0" @click="popup = 'domains'"
                      class="text-xs text-blue-300 hover:text-blue-200 font-medium transition cursor-pointer">
                [+{{ otherDomainCount }} more]
              </button>
              <button v-else-if="!primaryDomain" @click="popup = 'domains'"
                      class="text-xs text-blue-300 hover:text-blue-200 font-medium transition cursor-pointer">
                [+ add domain]
              </button>
              <button v-else @click="popup = 'domains'"
                      class="text-xs text-gray-500 hover:text-gray-400 transition cursor-pointer">[manage]</button>
            </div>
          </div>

          <!-- Analysis placeholder -->
          <div class="-mt-4 mx-4 mb-4 bg-white rounded-lg border border-brand-100 shadow-sm px-4 py-3">
            <p class="text-xs font-semibold text-brand-400 uppercase tracking-wide mb-1">Company Analysis</p>
            <p class="text-xs text-brand-200 italic">AI summary coming soon…</p>
          </div>
        </div>

        <!-- Contacts -->
        <div>
          <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2 px-1">Contacts</p>
          <div v-if="!contacts.length" class="bg-white rounded-xl border border-gray-200 px-4 py-4 text-sm text-gray-400 italic">No contacts linked.</div>
          <div v-else class="space-y-2">
            <a v-for="person in contacts" :key="person.id"
               :href="`/people/${person.id}`"
               class="flex items-center gap-3 bg-white rounded-xl border border-gray-200 px-4 py-3
                      hover:border-brand-300 hover:shadow-sm transition group">
              <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0 bg-gray-100 text-gray-600 border border-gray-100">
                {{ person.initials }}
              </div>
              <div class="flex-1 min-w-0">
                <p class="font-semibold text-gray-800 text-sm truncate group-hover:text-brand-700 transition">
                  {{ person.full_name }}
                </p>
                <p v-if="person.role" class="text-xs text-gray-400">{{ person.role }}</p>
              </div>
              <span class="text-xs text-brand-600 font-medium opacity-0 group-hover:opacity-100 transition shrink-0">Manage →</span>
            </a>
          </div>
        </div>

        <!-- External Accounts -->
        <div>
          <div class="flex items-center justify-between mb-2 px-1">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">External Accounts</p>
            <button v-if="canWrite" @click="popup = 'add-account'"
                    class="text-xs font-medium text-brand-600 hover:text-brand-700 border border-brand-200
                           hover:border-brand-400 px-3 py-1 rounded-full transition">
              + Add
            </button>
          </div>
          <div v-if="!accounts.length" class="bg-white rounded-xl border border-gray-200 px-4 py-4 text-sm text-gray-400 italic">
            No external accounts linked.
          </div>
          <div v-else class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
            <div v-for="account in accounts" :key="account.id" class="flex items-center gap-2 px-4 py-2.5">
              <ChannelBadge :type="account.system_type" :label="false" />
              <span v-if="account.system_slug !== 'default'" class="text-xs text-gray-700 shrink-0">{{ account.system_slug }}</span>
              <span class="font-mono text-sm text-gray-700 truncate flex-1">{{ account.external_id }}</span>
              <form v-if="canWrite" @submit.prevent="deleteAccount(account)" class="shrink-0">
                <button class="text-xs text-red-400 hover:text-red-600 font-bold">✕</button>
              </form>
            </div>
          </div>
        </div>

        <!-- Notes -->
        <NotesSection
          :notes="notes"
          linkableType="App\Models\Company"
          :linkableId="company.id"
        />

        <!-- Merged companies -->
        <div v-if="mergedCompanies.length">
          <div class="flex items-center justify-between mb-2 px-1">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">
              Merged
              <span class="ml-1 px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-500 text-xs font-bold">{{ mergedCompanies.length }}</span>
            </p>
          </div>
          <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
            <div v-for="mc in mergedCompanies" :key="mc.id" class="flex items-center gap-3 px-4 py-3">
              <div class="flex-1 min-w-0">
                <a :href="`/companies/${mc.id}`" class="font-medium text-sm text-gray-800 hover:text-brand-700 truncate block">
                  {{ mc.name }}
                </a>
                <p v-if="mc.primary_domain" class="text-xs text-gray-400 font-mono truncate">{{ mc.primary_domain }}</p>
                <p class="text-xs text-gray-400 mt-0.5">
                  {{ mc.accounts_count }} account{{ mc.accounts_count === 1 ? '' : 's' }}
                  · {{ mc.people_count }} contact{{ mc.people_count === 1 ? '' : 's' }}
                </p>
              </div>
              <form v-if="canWrite" @submit.prevent="unmerge(mc)" class="shrink-0">
                <button type="submit" class="text-xs text-gray-400 hover:text-red-600 transition">Unmerge</button>
              </form>
            </div>
          </div>
        </div>

      </div><!-- /LEFT -->

      <!-- RIGHT COLUMN (2/3) -->
      <div class="col-span-1 md:col-span-2 space-y-5">

        <!-- Segmentation -->
        <div class="w-full">
          <div class="flex items-center justify-between mb-3">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Segmentation</p>
            <button v-if="availableBrands.length && canWrite" @click="popup = 'add-brand'"
                    class="text-xs font-medium text-brand-600 hover:text-brand-700 border border-brand-200
                           hover:border-brand-400 px-3 py-1 rounded-full transition">
              + Add Segmentation
            </button>
          </div>
          <p v-if="!brandStatuses.length" class="text-sm text-gray-400 italic">No brand statuses yet.</p>
          <div v-else class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
            <BrandStatusCard
              v-for="bs in brandStatuses" :key="bs.id"
              :status="bs"
              :companyId="company.id"
              :canWrite="canWrite"
            />
          </div>
        </div>

        <!-- Services -->
        <div v-if="Object.keys(serviceSystems).length" class="bg-white rounded-xl border border-gray-200 overflow-hidden">
          <div class="flex items-center border-b border-gray-100 px-4 pt-1">
            <button v-for="(sys, slug) in serviceSystems" :key="slug"
                    @click="activeSvcTab = slug"
                    class="flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium border-b-2 transition whitespace-nowrap mr-1"
                    :class="activeSvcTab === slug ? 'border-brand-500 text-brand-700' : 'border-transparent text-gray-400 hover:text-gray-700'">
              <ChannelBadge :type="sys.system_type || 'generic'" :label="false" />
              {{ slug }}
            </button>
          </div>
          <template v-for="(sys, slug) in serviceSystems" :key="slug">
            <div v-show="activeSvcTab === slug">
              <ServicesWidget :sys="sys" :slug="slug" />
            </div>
          </template>
        </div>

        <!-- Timeline -->
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
          <!-- Tab bar -->
          <div class="flex items-center border-b border-gray-100 px-4 pt-1">
            <button v-for="tab in timelineTabs" :key="tab.key"
                    @click="setTimelineTab(tab.key)"
                    class="px-4 py-2.5 text-sm font-medium border-b-2 transition whitespace-nowrap mr-1"
                    :class="activeTimelineTab === tab.key
                      ? (tab.key === 'filtered' ? 'border-red-400 text-red-600' : 'border-brand-500 text-brand-700')
                      : (tab.key === 'filtered' ? 'border-transparent text-gray-300 hover:text-red-500' : 'border-transparent text-gray-400 hover:text-gray-700')">
              {{ tab.label }}
            </button>
          </div>

          <!-- Filter bar -->
          <div class="px-5 pt-3 pb-3 border-b border-gray-100">
            <div class="flex items-center gap-3">
              <!-- Conversations filter dropdown -->
              <div v-show="activeTimelineTab === 'conversations'" class="relative" ref="convDropdownRef">
                <button @click="convDropdownOpen = !convDropdownOpen"
                        class="flex items-center gap-2 px-3 py-1.5 rounded-lg border border-gray-200 bg-white
                               text-xs text-gray-600 hover:border-gray-300 transition min-w-[130px]">
                  <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M7 8h10M11 12h2"/>
                  </svg>
                  <span class="flex-1 text-left">{{ convDropdownLabel }}</span>
                  <svg class="w-3 h-3 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                  </svg>
                </button>
                <div v-show="convDropdownOpen" class="absolute left-0 top-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg z-20 py-1.5 w-64">
                  <label class="flex items-center gap-2.5 px-3 py-2 hover:bg-gray-50 cursor-pointer select-none">
                    <input type="checkbox" class="rounded border-gray-300" :checked="!tlActiveSystems.length && !tlShowFiltered" @change="tlConvAll">
                    <span class="text-sm text-gray-700 font-medium">All</span>
                  </label>
                  <label v-if="filteredConvCount > 0" class="flex items-center gap-2.5 px-3 py-2 hover:bg-gray-50 cursor-pointer select-none">
                    <input type="checkbox" class="rounded border-gray-300" :checked="tlShowFiltered" @change="tlToggleFiltered">
                    <span class="w-2 h-2 rounded-full bg-red-400 shrink-0"></span>
                    <span class="text-sm text-gray-700">Filtered ({{ filteredConvCount }})</span>
                  </label>
                  <template v-if="convSystems.length">
                    <div class="border-t border-gray-100 my-1"></div>
                    <label v-for="sys in convSystems" :key="sys.channel_type + '|' + sys.system_slug"
                           class="flex items-center gap-2.5 px-3 py-2 hover:bg-gray-50 cursor-pointer select-none">
                      <input type="checkbox" class="rounded border-gray-300"
                             :checked="tlActiveSystems.includes(sys.channel_type + '|' + sys.system_slug)"
                             @change="tlToggleSystem(sys.channel_type + '|' + sys.system_slug)">
                      <ChannelBadge :type="sys.channel_type" :label="false" />
                      <span class="text-xs text-gray-700 truncate">{{ sys.system_slug }}</span>
                    </label>
                  </template>
                </div>
              </div>

              <!-- Activity filter dropdown -->
              <div v-show="activeTimelineTab === 'activity'" class="relative" ref="actDropdownRef">
                <button @click="actDropdownOpen = !actDropdownOpen"
                        class="flex items-center gap-2 px-3 py-1.5 rounded-lg border border-gray-200 bg-white
                               text-xs text-gray-600 hover:border-gray-300 transition min-w-[130px]">
                  <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M7 8h10M11 12h2"/>
                  </svg>
                  <span class="flex-1 text-left">{{ actDropdownLabel }}</span>
                  <svg class="w-3 h-3 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                  </svg>
                </button>
                <div v-show="actDropdownOpen" class="absolute left-0 top-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg z-20 py-1.5 w-52">
                  <label class="flex items-center gap-2.5 px-3 py-2 hover:bg-gray-50 cursor-pointer select-none">
                    <input type="checkbox" class="rounded border-gray-300" :checked="!tlActiveActTypes.length" @change="tlActAll">
                    <span class="text-sm text-gray-700 font-medium">All</span>
                  </label>
                  <template v-if="activityTypes.length">
                    <div class="border-t border-gray-100 my-1"></div>
                    <label v-for="t in activityTypes" :key="t"
                           class="flex items-center gap-2.5 px-3 py-2 hover:bg-gray-50 cursor-pointer select-none">
                      <input type="checkbox" class="rounded border-gray-300"
                             :checked="tlActiveActTypes.includes(t)"
                             @change="tlToggleActType(t)">
                      <span class="w-2 h-2 rounded-full shrink-0" :class="typeColors[t] || 'bg-slate-300'"></span>
                      <span class="text-sm text-gray-700">{{ formatType(t) }}</span>
                    </label>
                  </template>
                </div>
              </div>

              <div class="flex-1"></div>

              <!-- Clear -->
              <button v-show="hasTimelineFilters" @click="resetTimelineFilters"
                      class="text-xs text-gray-400 hover:text-gray-600 transition whitespace-nowrap">
                ✕ Clear
              </button>

              <!-- Date range -->
              <input ref="dateRangeInput" type="text" placeholder="Date range…"
                     class="text-xs border border-gray-200 rounded-lg px-2.5 py-1.5 text-gray-600 bg-white
                            focus:outline-none cursor-pointer w-44" readonly>
            </div>
          </div>

          <!-- Timeline body -->
          <div class="relative px-4 py-2 min-h-[120px]">
            <div class="absolute inset-y-0 left-1/2 -translate-x-1/2 w-px bg-gray-200 pointer-events-none z-0"></div>
            <div class="relative z-10">
              <Timeline
                :activities="tlItems"
                :initialCursor="tlNextCursor"
                :timelineUrl="timelineUrl"
                :showPersonLink="true"
                gridClass="grid grid-cols-[1fr_2rem_1fr]"
                @openModal="openModal"
              />
            </div>
          </div>
        </div>

      </div><!-- /RIGHT -->

    </div><!-- /main grid -->

    <!-- POPUPS -->

    <!-- Domains -->
    <Modal :show="popup === 'domains'" @close="popup = null" size="md">
      <template #header>Domains</template>
      <ul class="divide-y divide-gray-50 max-h-60 overflow-y-auto">
        <li v-for="domain in domains" :key="domain.id" class="px-5 py-3 flex items-center justify-between gap-3">
          <div class="flex items-center gap-2 min-w-0">
            <span class="font-mono text-sm text-gray-700 truncate">{{ domain.domain }}</span>
            <span v-if="domain.is_primary" class="px-1.5 py-0.5 rounded text-xs bg-green-100 text-green-700 shrink-0">primary</span>
          </div>
          <div v-if="canWrite" class="flex items-center gap-3 shrink-0">
            <form v-if="!domain.is_primary" @submit.prevent="setPrimaryDomain(domain)">
              <button class="text-xs text-gray-400 hover:text-brand-600 whitespace-nowrap transition">set primary</button>
            </form>
            <form @submit.prevent="deleteDomain(domain)">
              <button class="text-xs text-red-400 hover:text-red-600 font-bold">✕</button>
            </form>
          </div>
        </li>
        <li v-if="!domains.length" class="px-5 py-5 text-sm text-gray-400 italic text-center">No domains yet.</li>
      </ul>
      <div v-if="canWrite" class="px-5 py-4 bg-gray-50 border-t border-gray-100">
        <form @submit.prevent="addDomain" class="flex gap-2">
          <input v-model="newDomain" type="text" placeholder="example.com"
                 class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-400">
          <button class="px-4 py-2 bg-brand-600 text-white text-sm rounded-lg hover:bg-brand-700 transition">Add</button>
        </form>
      </div>
    </Modal>

    <!-- Aliases -->
    <Modal :show="popup === 'aliases'" @close="popup = null" size="md">
      <template #header>Aliases</template>
      <ul class="divide-y divide-gray-50 max-h-60 overflow-y-auto">
        <li v-for="alias in aliases" :key="alias.id" class="px-5 py-3 flex items-center justify-between gap-3">
          <div class="flex items-center gap-2 min-w-0">
            <span class="text-sm text-gray-700 truncate">{{ alias.alias }}</span>
            <span v-if="alias.is_primary" class="px-1.5 py-0.5 rounded text-xs bg-green-100 text-green-700 shrink-0">primary</span>
          </div>
          <div v-if="canWrite" class="flex items-center gap-3 shrink-0">
            <form v-if="!alias.is_primary" @submit.prevent="setPrimaryAlias(alias)">
              <button class="text-xs text-gray-400 hover:text-brand-600 whitespace-nowrap transition">set primary</button>
            </form>
            <form @submit.prevent="deleteAlias(alias)">
              <button class="text-xs text-red-400 hover:text-red-600 font-bold">✕</button>
            </form>
          </div>
        </li>
        <li v-if="!aliases.length" class="px-5 py-5 text-sm text-gray-400 italic text-center">No aliases yet.</li>
      </ul>
      <div v-if="canWrite" class="px-5 py-4 bg-gray-50 border-t border-gray-100">
        <form @submit.prevent="addAlias" class="flex gap-2">
          <input v-model="newAlias" type="text" placeholder="Alias…"
                 class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-400">
          <button class="px-4 py-2 bg-brand-600 text-white text-sm rounded-lg hover:bg-brand-700 transition font-medium">Add</button>
        </form>
      </div>
    </Modal>

    <!-- Add External Account -->
    <Modal v-if="canWrite" :show="popup === 'add-account'" @close="popup = null" size="sm">
      <template #header>Add External Account</template>
      <form @submit.prevent="addAccount" class="px-5 py-4 space-y-3">
        <div>
          <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide block mb-1">System Type</label>
          <input v-model="newAccount.system_type" type="text" placeholder="whmcs, metricscube, …"
                 class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
        </div>
        <div>
          <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide block mb-1">
            System Slug <span class="normal-case font-normal text-gray-400">(optional, for multi-instance)</span>
          </label>
          <input v-model="newAccount.system_slug" type="text" placeholder="default"
                 class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
        </div>
        <div>
          <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide block mb-1">External ID</label>
          <input v-model="newAccount.external_id" type="text" placeholder="12345"
                 class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
        </div>
        <button class="w-full py-2 bg-brand-600 text-white font-semibold text-sm rounded-lg hover:bg-brand-700 transition">
          Add Account
        </button>
      </form>
    </Modal>

    <!-- Add Brand Status -->
    <Modal v-if="canWrite && availableBrands.length" :show="popup === 'add-brand'" @close="popup = null" size="sm">
      <template #header>Add Brand Status</template>
      <form @submit.prevent="addBrandStatus" class="px-5 py-4 space-y-3">
        <div>
          <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide block mb-1">Brand / Product</label>
          <select v-model="newBrand.brand_product_id"
                  class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-brand-400">
            <option v-for="bp in availableBrands" :key="bp.id" :value="bp.id">
              {{ bp.name }}{{ bp.variant ? ' · ' + bp.variant : '' }}
            </option>
          </select>
        </div>
        <div>
          <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide block mb-1">Stage</label>
          <select v-model="newBrand.stage"
                  class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-brand-400">
            <option v-for="s in stages" :key="s" :value="s">{{ s }}</option>
          </select>
        </div>
        <div>
          <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide block mb-1">Score (1-10)</label>
          <input v-model.number="newBrand.evaluation_score" type="number" min="1" max="10" placeholder="—"
                 class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
        </div>
        <button class="w-full py-2 bg-brand-600 text-white font-semibold text-sm rounded-lg hover:bg-brand-700 transition">
          Add Brand Status
        </button>
      </form>
    </Modal>

    <ConversationQuickView :show="showConvModal" :src="convModalSrc" @close="showConvModal = false" />

    <FilterRuleModal :show="showFilterModal" :fetchUrl="filterFetchUrl" :submitUrl="'/data-relations/filtering/apply-rule'"
                     title="Filter Company" @close="showFilterModal = false" />

  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import AppLayout from '../../layouts/AppLayout.vue'
import Modal from '../../components/Modal.vue'
import NotesSection from '../../components/NotesSection.vue'
import ChannelBadge from '../../components/ChannelBadge.vue'
import ScoreRing from '../../components/ScoreRing.vue'
import StageBadge from '../../components/StageBadge.vue'
import Timeline from '../../components/Timeline.vue'
import ConversationQuickView from '../../components/ConversationQuickView.vue'
import FilterRuleModal from '../../components/FilterRuleModal.vue'

const props = defineProps({
  company: Object,
  domains: Array,
  aliases: Array,
  accounts: Array,
  brandStatuses: Array,
  contacts: Array,
  mergedCompanies: Array,
  notes: Array,
  primaryDomain: Object,
  otherDomainCount: Number,
  primaryAlias: Object,
  nonPrimaryAliasCount: Number,
  serviceSystems: Object,
  availableBrands: Array,
  timeline: Object,
  convSystems: Array,
  filteredConvCount: Number,
  activityTypes: Array,
  typeColors: Object,
  backLink: Object,
  filterModalUrl: String,
})

const page = usePage()
const canWrite = computed(() => page.props.auth?.permissions?.data_write)

// Popup state
const popup = ref(null)
const showConvModal = ref(false)
const convModalSrc = ref('')
const showFilterModal = ref(false)
const filterFetchUrl = ref('')
const newDomain = ref('')
const newAlias = ref('')
const newAccount = ref({ system_type: '', system_slug: '', external_id: '' })
const newBrand = ref({ brand_product_id: props.availableBrands[0]?.id, stage: 'lead', evaluation_score: null })
const stages = ['lead', 'prospect', 'trial', 'active', 'churned']

// Services tabs
const activeSvcTab = ref(Object.keys(props.serviceSystems)[0] || '')

// Timeline state
const activeTimelineTab = ref('conversations')
const tlActiveSystems = ref([])
const tlShowFiltered = ref(false)
const tlActiveActTypes = ref([])
const tlItems = ref([...props.timeline.items])
const tlNextCursor = ref(props.timeline.nextCursor)
const convDropdownOpen = ref(false)
const actDropdownOpen = ref(false)
const convDropdownRef = ref(null)
const actDropdownRef = ref(null)
const dateRangeInput = ref(null)
let dateFrom = ''
let dateTo = ''

const timelineTabs = [
  { key: 'conversations', label: 'Conversations' },
  { key: 'activity', label: 'Activity' },
  { key: 'all', label: 'All' },
  { key: 'filtered', label: 'Filtered' },
]

const convDropdownLabel = computed(() => {
  const total = tlActiveSystems.value.length + (tlShowFiltered.value ? 1 : 0)
  if (total === 0) return 'All'
  if (total === 1) {
    if (tlShowFiltered.value && !tlActiveSystems.value.length) return 'Filtered'
    return tlActiveSystems.value[0]?.split('|')[1] || 'All'
  }
  return total + ' filters'
})

const actDropdownLabel = computed(() => {
  if (!tlActiveActTypes.value.length) return 'All'
  if (tlActiveActTypes.value.length === 1) return tlActiveActTypes.value[0].replace(/_/g, ' ')
  return tlActiveActTypes.value.length + ' types'
})

const hasTimelineFilters = computed(() =>
  tlActiveSystems.value.length > 0 || tlShowFiltered.value || tlActiveActTypes.value.length > 0 || dateFrom || dateTo
)

const timelineUrl = computed(() => {
  const p = new URLSearchParams()
  if (dateFrom) p.set('from', dateFrom)
  if (dateTo) p.set('to', dateTo)

  if (activeTimelineTab.value === 'conversations') {
    p.append('types[]', 'conversation')
    tlActiveSystems.value.forEach(s => p.append('systems[]', s))
    if (tlShowFiltered.value) p.set('is_filtered', '1')
  } else if (activeTimelineTab.value === 'activity') {
    tlActiveActTypes.value.forEach(t => p.append('types[]', t))
  } else if (activeTimelineTab.value === 'filtered') {
    p.set('is_filtered', '1')
  }

  return `/companies/${props.company.id}/timeline?${p}`
})

function setTimelineTab(tab) {
  activeTimelineTab.value = tab
  tlActiveSystems.value = []
  tlShowFiltered.value = false
  tlActiveActTypes.value = []
  convDropdownOpen.value = false
  actDropdownOpen.value = false
  resetTimeline()
}

function tlConvAll() {
  tlActiveSystems.value = []
  tlShowFiltered.value = false
  resetTimeline()
}

function tlToggleFiltered() {
  tlShowFiltered.value = !tlShowFiltered.value
  if (!tlActiveSystems.value.length && !tlShowFiltered.value) {
    // nothing selected, revert to all
  }
  resetTimeline()
}

function tlToggleSystem(val) {
  const idx = tlActiveSystems.value.indexOf(val)
  if (idx === -1) tlActiveSystems.value.push(val)
  else tlActiveSystems.value.splice(idx, 1)
  resetTimeline()
}

function tlActAll() {
  tlActiveActTypes.value = []
  resetTimeline()
}

function tlToggleActType(t) {
  const idx = tlActiveActTypes.value.indexOf(t)
  if (idx === -1) tlActiveActTypes.value.push(t)
  else tlActiveActTypes.value.splice(idx, 1)
  resetTimeline()
}

function resetTimelineFilters() {
  setTimelineTab('all')
}

async function resetTimeline() {
  try {
    const url = new URL(timelineUrl.value, window.location.origin)
    url.searchParams.set('json', '1')
    const resp = await fetch(url.toString(), {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
    const data = await resp.json()
    tlItems.value = data.items
    tlNextCursor.value = data.nextCursor || null
  } catch (e) {
    console.error('Timeline reset error:', e)
  }
}

function formatType(t) {
  return t.charAt(0).toUpperCase() + t.slice(1).replace(/_/g, ' ')
}

// Click outside to close dropdowns
function handleClickOutside(e) {
  if (convDropdownRef.value && !convDropdownRef.value.contains(e.target)) convDropdownOpen.value = false
  if (actDropdownRef.value && !actDropdownRef.value.contains(e.target)) actDropdownOpen.value = false
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
  // Init date range picker if available
  if (window.drp && dateRangeInput.value) {
    window.drp.init(dateRangeInput.value, (from, to) => {
      dateFrom = from
      dateTo = to
      resetTimeline()
    })
  }
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})

// Modal open for timeline items
function openModal(url) {
  convModalSrc.value = url
  showConvModal.value = true
}

// Filter modal
function showCompanyFilterModal() {
  filterFetchUrl.value = `${props.filterModalUrl}?ids[]=${props.company.id}`
  showFilterModal.value = true
}

// CRUD actions
function addDomain() {
  if (!newDomain.value.trim()) return
  router.post(`/companies/${props.company.id}/domains`, { domain: newDomain.value }, {
    preserveScroll: true,
    onSuccess: () => { newDomain.value = ''; popup.value = null },
  })
}

function deleteDomain(domain) {
  if (!confirm(`Remove ${domain.domain}?`)) return
  router.delete(`/companies/${props.company.id}/domains/${domain.id}`, { preserveScroll: true })
}

function setPrimaryDomain(domain) {
  router.patch(`/companies/${props.company.id}/domains/${domain.id}/primary`, {}, { preserveScroll: true })
}

function addAlias() {
  if (!newAlias.value.trim()) return
  router.post(`/companies/${props.company.id}/aliases`, { alias: newAlias.value }, {
    preserveScroll: true,
    onSuccess: () => { newAlias.value = ''; popup.value = null },
  })
}

function deleteAlias(alias) {
  if (!confirm(`Remove ${alias.alias}?`)) return
  router.delete(`/companies/${props.company.id}/aliases/${alias.id}`, { preserveScroll: true })
}

function setPrimaryAlias(alias) {
  router.patch(`/companies/${props.company.id}/aliases/${alias.id}/primary`, {}, { preserveScroll: true })
}

function addAccount() {
  router.post(`/companies/${props.company.id}/accounts`, newAccount.value, {
    preserveScroll: true,
    onSuccess: () => {
      newAccount.value = { system_type: '', system_slug: '', external_id: '' }
      popup.value = null
    },
  })
}

function deleteAccount(account) {
  if (!confirm('Remove this account?')) return
  router.delete(`/companies/${props.company.id}/accounts/${account.id}`, { preserveScroll: true })
}

function addBrandStatus() {
  router.post(`/companies/${props.company.id}/brand-statuses`, newBrand.value, {
    preserveScroll: true,
    onSuccess: () => {
      newBrand.value = { brand_product_id: props.availableBrands[0]?.id, stage: 'lead', evaluation_score: null }
      popup.value = null
    },
  })
}

function unmerge(mc) {
  if (!confirm(`Unmerge ${mc.name}? It will reappear in the companies list.`)) return
  router.post(`/companies/${mc.id}/unmerge`, {}, { preserveScroll: true })
}

// Brand Status Card sub-component
const BrandStatusCard = {
  props: ['status', 'companyId', 'canWrite'],
  components: { ScoreRing, StageBadge },
  data() {
    return { editing: false }
  },
  computed: {
    bgClass() {
      return {
        lead: 'bg-blue-50 border-blue-200',
        prospect: 'bg-purple-50 border-purple-200',
        trial: 'bg-yellow-50 border-yellow-200',
        active: 'bg-green-50 border-green-200',
        churned: 'bg-red-50 border-red-200',
      }[this.status.stage?.toLowerCase()] || 'bg-white border-gray-200'
    },
  },
  methods: {
    updateStatus(e) {
      const form = e.target
      const data = new FormData(form)
      router.patch(`/companies/${this.companyId}/brand-statuses/${this.status.id}`, {
        stage: data.get('stage'),
        evaluation_score: data.get('evaluation_score') || null,
        evaluation_notes: data.get('evaluation_notes') || null,
      }, {
        preserveScroll: true,
        onSuccess: () => { this.editing = false },
      })
    },
    removeStatus() {
      router.delete(`/companies/${this.companyId}/brand-statuses/${this.status.id}`, { preserveScroll: true })
    },
  },
  template: `
    <div :class="bgClass" class="rounded-xl border p-4">
      <div class="flex items-start justify-between mb-3">
        <div>
          <p class="font-semibold text-gray-900 text-sm">{{ status.brand_product?.name ?? '(deleted)' }}</p>
          <p v-if="status.brand_product?.variant" class="text-xs text-gray-400">{{ status.brand_product.variant }}</p>
        </div>
        <StageBadge :stage="status.stage" />
      </div>
      <div class="flex items-end justify-between">
        <div>
          <ScoreRing v-if="status.evaluation_score !== null" :score="status.evaluation_score" />
          <div v-else class="w-16 h-16 rounded-full border-4 border-gray-100 flex items-center justify-center">
            <span class="text-2xl font-bold text-gray-200">—</span>
          </div>
        </div>
        <div class="text-right text-xs text-gray-400">
          <p v-if="status.last_evaluated_at">{{ status.last_evaluated_at }}</p>
          <div v-if="canWrite" class="flex items-center gap-3">
            <button v-if="status.brand_product" @click="editing = !editing"
                    class="text-brand-600 hover:underline mt-1">Edit</button>
            <button @click="removeStatus" class="text-red-400 hover:text-red-600 text-xs mt-1">
              {{ status.brand_product ? 'Remove' : 'Remove (deleted product)' }}
            </button>
          </div>
        </div>
      </div>
      <p v-if="status.evaluation_notes" class="text-xs text-gray-500 mt-2 line-clamp-2">{{ status.evaluation_notes }}</p>
      <div v-if="canWrite && editing" class="mt-3 pt-3 border-t border-gray-100">
        <form @submit.prevent="updateStatus" class="space-y-2">
          <div class="flex gap-2">
            <select name="stage" class="flex-1 text-xs border border-gray-200 rounded px-2 py-1.5 bg-white">
              <option v-for="s in ['lead','prospect','trial','active','churned']" :key="s" :value="s" :selected="status.stage === s">{{ s }}</option>
            </select>
            <input type="number" name="evaluation_score" min="1" max="10"
                   :value="status.evaluation_score" placeholder="Score"
                   class="w-16 text-xs border border-gray-200 rounded px-2 py-1.5">
          </div>
          <input type="text" name="evaluation_notes" :value="status.evaluation_notes"
                 placeholder="Notes…" class="w-full text-xs border border-gray-200 rounded px-2 py-1.5">
          <button class="w-full py-1.5 bg-brand-600 text-white text-xs rounded hover:bg-brand-700 transition">Save</button>
        </form>
      </div>
    </div>
  `,
}

// Services Widget sub-component (replaces WHMCS Blade widget)
const ServicesWidget = {
  props: ['sys', 'slug'],
  computed: {
    statusClass() {
      return (status) => ({
        active: 'bg-green-100 text-green-700',
        pending: 'bg-yellow-100 text-yellow-700',
        suspended: 'bg-red-100 text-red-600',
      }[status?.toLowerCase()] || 'bg-gray-100 text-gray-500')
    },
    dotClass() {
      return (status) => ({
        active: 'bg-green-400',
        pending: 'bg-yellow-400',
        suspended: 'bg-red-400',
      }[status?.toLowerCase()] || 'bg-gray-300')
    },
  },
  methods: {
    formatRevenue(val) {
      return '$' + Number(val || 0).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
    },
    formatServiceRevenue(val) {
      return '$' + Number(val || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    },
    formatDate(d) {
      if (!d) return '—'
      const date = new Date(d)
      return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' })
    },
  },
  template: `
    <div>
      <div class="grid grid-cols-3 divide-x divide-gray-100 border-b border-gray-100">
        <div class="px-5 py-4">
          <p class="text-xs text-gray-400 uppercase tracking-wide font-semibold mb-1">Revenue</p>
          <p class="text-2xl font-bold text-gray-900 tabular-nums">{{ formatRevenue(sys.revenue) }}</p>
          <p class="text-xs text-gray-400 mt-0.5">lifetime</p>
        </div>
        <div class="px-5 py-4">
          <p class="text-xs text-gray-400 uppercase tracking-wide font-semibold mb-1">Active</p>
          <p class="text-2xl font-bold text-green-600 tabular-nums">{{ sys.active }}</p>
          <p class="text-xs text-gray-400 mt-0.5">services</p>
        </div>
        <div class="px-5 py-4">
          <p class="text-xs text-gray-400 uppercase tracking-wide font-semibold mb-1">Total</p>
          <p class="text-2xl font-bold text-gray-900 tabular-nums">{{ sys.total }}</p>
          <p class="text-xs text-gray-400 mt-0.5">all services</p>
        </div>
      </div>
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-100">
          <tr>
            <th class="px-5 py-2 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">Product</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">Status</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">Since</th>
            <th class="px-3 py-2 text-right text-xs font-semibold text-gray-400 uppercase tracking-wide">Renewals</th>
            <th class="px-5 py-2 text-right text-xs font-semibold text-gray-400 uppercase tracking-wide">Revenue</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-for="(svc, idx) in sys.services" :key="idx" class="hover:bg-gray-50/60">
            <td class="px-5 py-2.5 font-medium text-gray-800">{{ svc.product_name || '—' }}</td>
            <td class="px-3 py-2.5">
              <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-xs font-medium" :class="statusClass(svc.status)">
                <span class="w-1.5 h-1.5 rounded-full" :class="dotClass(svc.status)"></span>
                {{ (svc.status || '—').charAt(0).toUpperCase() + (svc.status || '—').slice(1) }}
              </span>
            </td>
            <td class="px-3 py-2.5 text-xs text-gray-500">{{ formatDate(svc.start_date) }}</td>
            <td class="px-3 py-2.5 text-xs text-gray-500 text-right tabular-nums">{{ svc.renewal_count || 0 }}×</td>
            <td class="px-5 py-2.5 text-right font-semibold text-gray-800 tabular-nums">{{ formatServiceRevenue(svc.total_revenue) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  `,
}
</script>
