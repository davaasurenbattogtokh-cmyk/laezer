import {OrderError} from './orders.js';
export function authorize(actor,role){if(!actor)throw new OrderError('Нэвтрэх шаардлагатай.',401);if(role&&actor.role!==role)throw new OrderError('Энэ үйлдлийг хийх эрхгүй байна.',403);return actor}
export function sameOrigin(request){const origin=request.headers.get('origin');if(!origin||origin!==new URL(request.url).origin)throw new OrderError('Зөвшөөрөгдөөгүй хүсэлт.',403)}
