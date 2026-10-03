import history from './history'
import thoughts from './thoughts'
import care from './care'
import skills from './skills'
import appearance from './appearance'
const pets = {
    history: Object.assign(history, history),
thoughts: Object.assign(thoughts, thoughts),
care: Object.assign(care, care),
skills: Object.assign(skills, skills),
appearance: Object.assign(appearance, appearance),
}

export default pets