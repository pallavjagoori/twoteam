import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import App from '../src/App.vue';

describe('App', () => {
  it('identifies the platform foundation', () => {
    const wrapper = mount(App);

    expect(wrapper.text()).toContain('Compatibility platform foundation');
  });
});
