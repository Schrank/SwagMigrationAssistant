import { object } from 'src/core/service/util.service';
import { MIGRATION_STATUS } from '../service/migration/swag-migration-worker-status-manager.service';
import SimpleStateManagementStore from './SimpleStateManagementStore';

/**
 * To get this store in the browser console execute:
 * migrationProcessStore = Shopware.Application.getContainer('factory').state.getStore('migrationProcess')
 */
class MigrationProcessStore extends SimpleStateManagementStore {
    constructor() {
        super();
        this.state = {
            connectionId: null,
            environmentInformation: null,
            runId: null,
            isMigrating: false,
            entityGroups: [],
            currentEntityGroupId: '',
            statusIndex: MIGRATION_STATUS.WAITING,
            errors: []
        };
    }

    // setters (mutations)
    /**
     * @param {string} value
     */
    setConnectionId(value) {
        this._checkDebugging(this.state.connectionId, value);
        this.state.connectionId = value;
    }

    /**
     * @param {Object} newEnvironmentInformation
     */
    setEnvironmentInformation(newEnvironmentInformation) {
        const newEnvironmentInformationCopy = object.deepCopyObject(newEnvironmentInformation);
        this._checkDebugging(this.state.environmentInformation, newEnvironmentInformationCopy);
        this.state.environmentInformation = newEnvironmentInformationCopy;
    }

    /**
     * @param {string} value
     */
    setRunId(value) {
        this._checkDebugging(this.state.runId, value);
        this.state.runId = value;
    }

    /**
     * @param {boolean} value
     */
    setIsMigrating(value) {
        this._checkDebugging(this.state.isMigrating, value);
        this.state.isMigrating = value;
    }

    /**
     * @param {number} newIndex
     */
    setStatusIndex(newIndex) {
        this._checkDebugging(this.state.statusIndex, newIndex);
        this.state.statusIndex = newIndex;
    }

    setCurrentEntityGroupId(newId) {
        this._checkDebugging(this.state.currentEntityGroupId, newId);
        this.state.currentEntityGroupId = newId;
    }

    /**
     * @param {Object[]} entityGroups
     */
    setEntityGroups(entityGroups) {
        const entityGroupsCopy = object.deepCopyObject(entityGroups);
        this._checkDebugging(this.state.entityGroups, entityGroupsCopy);
        this.state.entityGroups = entityGroupsCopy;
    }

    /**
     * @param {string} entityName
     * @param {number} groupCurrentCount
     * @param {number} groupTotal
     */
    setEntityProgress(entityName, groupCurrentCount, groupTotal) {
        this._checkDebugging(this.state.entityGroups, entityName, groupCurrentCount, groupTotal);
        const targetGroup = this.getDisplayEntityGroups().find((group) => {
            for (let i = 0; i < group.entities.length; i += 1) {
                if (group.entities[i].entityName === entityName) {
                    return true;
                }
            }

            return false;
        });

        if (targetGroup === undefined) {
            return;
        }

        if (targetGroup.total !== groupTotal) {
            targetGroup.total = groupTotal;
        }

        targetGroup.currentCount = groupCurrentCount;
        this.setCurrentEntityGroupId(targetGroup.id);
    }

    resetProgress() {
        this._checkDebugging(this.state.entityGroups);
        this.state.entityGroups.forEach((data) => {
            data.currentCount = 0;
        });
    }

    /**
     * @param {Object[]} newErrors
     */
    setErrors(newErrors) {
        const newErrorsCopy = object.deepCopyObject(newErrors);
        this._checkDebugging(this.state.errors, newErrorsCopy);
        this.state.errors = newErrorsCopy;
    }

    /**
     * @param {Object} error
     */
    addError(error) {
        const errorCopy = object.deepCopyObject(error);
        this._checkDebugging(this.state.errors, errorCopy);
        this.state.errors.push(errorCopy);
    }


    // getters (to use as computed properties)
    /**
     * @returns {Object[]}
     */
    getDisplayEntityGroups() {
        if (this.state.statusIndex === MIGRATION_STATUS.PROCESS_MEDIA_FILES) {
            return this.state.entityGroups.filter((group) => {
                return group.id === 'processMediaFiles';
            });
        }

        return this.state.entityGroups.filter((group) => {
            return group.id !== 'processMediaFiles';
        });
    }
}

export default MigrationProcessStore;