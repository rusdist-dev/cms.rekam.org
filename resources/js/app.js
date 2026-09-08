import './api'

import Alpine from 'alpinejs'
import sort from '@alpinejs/sort'

import chart from './chart'
import contactDetail from './alpine/contactDetail'
import contentForm from './alpine/contentForm'
import initials from './initials'
import apiResource from './alpine/apiResource'
import editor from './alpine/editor'
import mediaPicker from './alpine/mediaPicker'
import multiSelect from './alpine/multiSelect'
import newsCategoryEditor from './alpine/newsCategoryEditor'
import repeater from './alpine/repeater'
import resourceForm from './alpine/resourceForm'
import roleForm from './alpine/roleForm'
import remoteSelect from './alpine/remoteSelect'
import resourceTable from './alpine/resourceTable'
import settingsForm from './alpine/settingsForm'
import sidebar from './alpine/sidebar'
import sortableList from './alpine/sortableList'
import tagInput from './alpine/tagInput'
import taxonomyEditor from './alpine/taxonomyEditor'
import teamBoard from './alpine/teamBoard'
import tenantForm from './alpine/tenantForm'

Alpine.plugin(sort)

// $initials(name) — lets <x-avatar> work inside x-for without duplicating markup.
Alpine.magic('initials', () => initials)

// Alpine data components live in resources/js/alpine/ and are registered here.
// Anything longer than a few lines must not sit inline in a Blade file
// (context.md §3.9).
Alpine.data('apiResource', apiResource)
Alpine.data('chart', chart)
Alpine.data('contactDetail', contactDetail)
Alpine.data('contentForm', contentForm)
Alpine.data('editor', editor)
Alpine.data('mediaPicker', mediaPicker)
Alpine.data('multiSelect', multiSelect)
Alpine.data('newsCategoryEditor', newsCategoryEditor)
Alpine.data('repeater', repeater)
Alpine.data('resourceForm', resourceForm)
Alpine.data('roleForm', roleForm)
Alpine.data('remoteSelect', remoteSelect)
Alpine.data('resourceTable', resourceTable)
Alpine.data('settingsForm', settingsForm)
Alpine.data('sidebar', sidebar)
Alpine.data('sortableList', sortableList)
Alpine.data('tagInput', tagInput)
Alpine.data('taxonomyEditor', taxonomyEditor)
Alpine.data('teamBoard', teamBoard)
Alpine.data('tenantForm', tenantForm)

window.Alpine = Alpine

Alpine.start()
