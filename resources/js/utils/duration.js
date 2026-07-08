export const durationUtils = {
    get stayDurationLimits() {
        return window.__stayDurationLimits || { minDays: 1, maxDays: 85 };
    },

    handleStayDurationChange() {
        if (this.passengerData.stay_duration === 'Customize (Set Duration)') {
            this.openCustomDurationModal();
        }
    },

    openCustomDurationModal() {
        this.customDurationModalVisible = true;
        this.passengerData.customDurationDays = '';
        this.$nextTick(() => {
            const input = document.getElementById('customDurationDays');
            if (input) input.focus();
        });
    },

    closeCustomDurationModal() {
        this.customDurationModalVisible = false;
        this.passengerData.customDurationDays = '';
    },

    saveCustomDuration() {
        const days = parseInt(this.passengerData.customDurationDays);
        const { minDays, maxDays } = this.stayDurationLimits;

        if (isNaN(days) || days < minDays || days > maxDays) {
            alert(`Please enter a valid duration between ${minDays} and ${maxDays} days`);
            return;
        }

        this.passengerData.stay_duration = `Customized (${days} Days)`;
        this.passengerData.stay_duration_int = days;
        this.passengerData.stay_duration_display = `Customized (${days} Days)`;

        const select = document.querySelector('select[x-model="passengerData.stay_duration"]');
        if (select) {
            let customOption = Array.from(select.options).find(opt => opt.value.startsWith('Customized'));
            if (!customOption) {
                customOption = document.createElement('option');
                select.appendChild(customOption);
            }
            customOption.value = `Customized (${days} Days)`;
            customOption.textContent = `Customized (${days} Days)`;
            select.value = `Customized (${days} Days)`;
        }

        this.closeCustomDurationModal();
        this.calculatePassengerType();
    },

    parseStayDurationDays(stayDuration) {
        if (!stayDuration) return null;
        if (/^\d+$/.test(stayDuration)) {
            return parseInt(stayDuration, 10);
        }
        const match = stayDuration.match(/(\d+)\s*days?/i);
        return match ? parseInt(match[1], 10) : null;
    },

    getStayDurationValue() {
        return this.parseStayDurationDays(this.passengerData.stay_duration);
    },
};
