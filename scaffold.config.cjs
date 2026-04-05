/* eslint-disable @typescript-eslint/no-require-imports */

// eslint-disable-next-line no-undef
const { format } = require('date-fns')
// eslint-disable-next-line no-undef
const fs = require('node:fs')

function getLatestMigration() {
  const migrationDir = 'lib/Migration'
  const files = fs.readdirSync(migrationDir)
  const migrationFiles = files.sort((a, b) => a.localeCompare(b))
  const latestMigration = migrationFiles[migrationFiles.length - 1]
  const matches = /Version(\d+)/.exec(latestMigration)
  const version = (matches ? Number(matches[1]) : 0) + 1
  return version
}

// eslint-disable-next-line no-undef
module.exports = () => {
  const latestMigrationVersion = getLatestMigration()
  return {
    component: {
      templates: ['gen/component'],
      output: 'src/components',
    },
    view: {
      templates: ['gen/view'],
      output: 'src/views',
      subDir: false,
    },
    command: {
      templates: ['gen/command'],
      output: 'lib/Command',
      subDir: false,
    },
    model: {
      templates: ['gen/model'],
      output: 'lib/Db',
      subDir: false,
    },
    'task-queued': {
      templates: ['gen/task-queued'],
      output: 'lib/Cron',
      subDir: false,
    },
    'task-timed': {
      templates: ['gen/task-timed'],
      output: 'lib/Cron',
      subDir: false,
    },
    service: {
      templates: ['gen/service'],
      output: 'lib/Service',
      subDir: false,
    },
    util: {
      templates: ['gen/util'],
      output: 'lib/Util',
      subDir: false,
    },
    api: {
      templates: ['gen/api'],
      output: 'lib/Controller',
      subDir: false,
    },
    migration: {
      templates: ['gen/migration'],
      output: 'lib/Migration',
      name: '-',
      data: {
        version: latestMigrationVersion,
        dt: format(new Date(), 'yyyyMMddHHmmss'),
      },
    },
  }
}
